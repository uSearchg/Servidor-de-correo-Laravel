<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCorreo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SolicitudCorreoController extends Controller
{
    /**
     * @OA\Info(
     *     title="ServidorCorreo",
     *     version="1.0.0",
     *     description="API para gestionar el envío de correos"
     * )
     */
    /**
     * @OA\Post(
     *     path="/api/enviar-correo",
     *     summary="Almacena un correo en la cola para su envío posterior",
     *     description="Almacena una solicitud de correo en la tabla solicitudes_correo para ser procesada por un cronjob. Los campos cc, cco y adjunto son opcionales y pueden omitirse completamente en la solicitud.",
     *     tags={"Correos"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"asunto","cuerpo","remitente","destinatario","alias"},
     *             @OA\Property(property="asunto", type="string", example="Prueba de Correo", description="Asunto del correo"),
     *             @OA\Property(property="cuerpo", type="string", example="<p>Este es un correo de prueba</p>", description="Cuerpo del correo en HTML"),
     *             @OA\Property(property="remitente", type="string", format="email", example="sergio.garcia.memorandum@gmail.com", description="Correo del remitente (debe coincidir con el email de la cuenta seleccionada por alias)"),
     *             @OA\Property(property="destinatario", type="string", example="destinatario1@ejemplo.com;destinatario2@ejemplo.com", description="Correos de los destinatarios, separados por ;"),
     *             @OA\Property(property="alias", type="string", example="sergio", description="Alias de la cuenta de correo a usar (obligatorio). Opciones disponibles: sergio, ejemplo, ejemplo2"),
     *             @OA\Property(property="cc", type="string", example="copia1@ejemplo.com;copia2@ejemplo.com", description="Correos para copia (opcional), separados por ;"),
     *             @OA\Property(property="cco", type="string", example="copiaoculta1@ejemplo.com;copiaoculta2@ejemplo.com", description="Correos para copia oculta (opcional), separados por ;"),
     *             @OA\Property(property="adjunto", type="string", format="byte", example="data:application/pdf;base64,JVBERi0xLjQK...==", description="Archivo adjunto en base64 (opcional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Correo en cola exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="mensaje", type="string", example="Correo en cola exitosamente"),
     *             @OA\Property(property="id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado"))
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El campo asunto es obligatorio."))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al procesar la solicitud",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al procesar la solicitud"))
     *     )
     * )
     */
    public function almacenar(Request $request)
    {
        try {
            // Log para depurar los datos recibidos
            Log::info('Datos recibidos en la solicitud', [
                'input' => $request->all(),
                'json' => $request->json()->all(),
                'raw' => $request->getContent(),
                'encoding' => mb_detect_encoding($request->getContent()),
            ]);

            // Validar los campos obligatorios manualmente antes de procesar
            $requiredFields = ['asunto', 'cuerpo', 'remitente', 'destinatario', 'alias'];
            $missingFields = array_filter($requiredFields, fn($field) => !$request->has($field) || empty($request->input($field)));

            if (!empty($missingFields)) {
                Log::error('Faltan campos obligatorios', ['missing_fields' => $missingFields]);
                return response()->json([
                    'error' => 'Faltan campos obligatorios',
                    'details' => array_map(fn($field) => "El campo $field es obligatorio.", $missingFields),
                ], 422);
            }

            $data = $request->all();

            // Si hay un adjunto en Base64, decodificarlo y guardarlo
            if (isset($data['adjunto']) && !empty($data['adjunto'])) {
                // Eliminar el prefijo "data:...;base64," si está presente
                $base64String = preg_replace('/^data:[\w\/-]+;base64,/', '', $data['adjunto']);

                // Log para depurar la cadena Base64 antes y después del preg_replace
                Log::info('Procesando adjunto', [
                    'original_adjunto' => substr($data['adjunto'], 0, 100),
                    'base64_string' => substr($base64String, 0, 100),
                    'base64_string_full' => $base64String,
                    'base64_length' => strlen($base64String),
                    'base64_encoding' => mb_detect_encoding($base64String),
                ]);

                // Verificar si la cadena Base64 es válida
                $decodedContent = base64_decode($base64String, true);
                if ($decodedContent === false) {
                    Log::error('No se pudo decodificar la cadena Base64 del adjunto', [
                        'base64_string' => substr($base64String, 0, 100),
                        'base64_string_full' => $base64String,
                        'length' => strlen($base64String),
                    ]);
                    throw new \Exception('No se pudo decodificar el archivo adjunto. Asegúrate de que sea una cadena Base64 válida.');
                }

                // Verificar que la cadena decodificada y recodificada coincida con la original
                $recodedBase64 = base64_encode($decodedContent);
                if ($recodedBase64 !== $base64String) {
                    Log::error('La cadena Base64 no coincide después de decodificar y recodificar', [
                        'base64_string' => substr($base64String, 0, 100),
                        'base64_string_full' => $base64String,
                        'recoded_base64' => substr($recodedBase64, 0, 100),
                        'recoded_base64_full' => $recodedBase64,
                        'length' => strlen($base64String),
                    ]);
                    throw new \Exception('No se pudo decodificar el archivo adjunto. Asegúrate de que sea una cadena Base64 válida.');
                }

                // Verificar el tamaño del archivo decodificado
                $sizeInMB = strlen($decodedContent) / (1024 * 1024); // Tamaño en MB
                if ($sizeInMB > 18) { // Límite de 18 MB para evitar problemas con el correo
                    Log::error('El adjunto excede el tamaño máximo permitido', [
                        'size_in_mb' => $sizeInMB,
                    ]);
                    throw new \Exception('El adjunto excede el tamaño máximo permitido (18 MB).');
                }

                // Determinar el tipo de archivo y la extensión
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($decodedContent);

                // Log para depurar el tipo MIME detectado
                Log::info('Tipo MIME detectado', [
                    'mime_type' => $mimeType,
                ]);

                // Mapear tipos MIME a extensiones
                $extensionMap = [
                    'application/pdf' => 'pdf',
                    'text/plain' => 'txt',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                ];

                $extension = $extensionMap[$mimeType] ?? 'bin'; // Extensión por defecto si el tipo no está mapeado

                // Generar un nombre único para el archivo con la extensión correcta (en español)
                $fileName = 'documento_' . uniqid() . '.' . $extension;

                // Guardar el archivo temporalmente en storage/app/adjuntos
                Storage::put('adjuntos/' . $fileName, $decodedContent);

                // Log para confirmar que el archivo se guardó
                Log::info('Archivo guardado', [
                    'file_path' => Storage::path('adjuntos/' . $fileName),
                ]);

                // Actualizar el campo adjunto con la ruta del archivo y el tipo MIME
                $data['adjunto'] = Storage::path('adjuntos/' . $fileName);
                $data['adjunto_mime_type'] = $mimeType; // Guardar el tipo MIME para usarlo al enviar
            }

            // Validar y crear la solicitud de correo
            $validated = SolicitudCorreo::validateData($data);

            Log::info('Validación completada', $validated);

            $solicitud = SolicitudCorreo::create([
                'asunto' => $validated['asunto'],
                'cuerpo' => $validated['cuerpo'],
                'remitente' => $validated['remitente'],
                'destinatario' => $validated['destinatario'],
                'cc' => $validated['cc'] ?? null,
                'cco' => $validated['cco'] ?? null,
                'adjunto' => $validated['adjunto'] ?? null,
                'adjunto_mime_type' => $validated['adjunto_mime_type'] ?? null,
                'alias' => $validated['alias'],
                'fecha_hora_recepcion' => now(),
                'enviado' => false,
            ]);

            Log::info('Solicitud de correo creada', ['id' => $solicitud->id]);

            return response()->json([
                'mensaje' => 'Correo en cola exitosamente',
                'id' => $solicitud->id
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación', ['errors' => $e->errors()]);
            return response()->json([
                'error' => 'Error de validación',
                'details' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al procesar la solicitud', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }
}