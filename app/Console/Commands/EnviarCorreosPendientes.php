<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudCorreo;
use App\Models\CuentaCorreo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnviarCorreosPendientes extends Command
{
    protected $signature = 'correos:enviar-pendientes';
    protected $description = 'Procesa y envía los correos pendientes en la tabla solicitudes_correo';

    public function handle()
    {
        try {
            // Obtener los correos pendientes (enviado = false)
            $solicitudes = SolicitudCorreo::where('enviado', false)->get();

            if ($solicitudes->isEmpty()) {
                $this->info('No hay correos pendientes para enviar.');
                Log::info('No hay correos pendientes para enviar.');
                return 0;
            }

            foreach ($solicitudes as $solicitud) {
                try {
                    // Obtener la cuenta de correo asociada al alias
                    $cuenta = CuentaCorreo::where('alias', $solicitud->alias)->first();

                    if (!$cuenta) {
                        Log::error('Cuenta de correo no encontrada para el alias: ' . $solicitud->alias, ['solicitud_id' => $solicitud->id]);
                        $this->error('No se encontró la cuenta de correo para el alias: ' . $solicitud->alias);
                        continue;
                    }

                    // Configurar el transporte de correo dinámicamente
                    config([
                        'mail.mailers.smtp' => [
                            'transport' => 'smtp',
                            'host' => $cuenta->host,
                            'port' => $cuenta->port,
                            'encryption' => $cuenta->encryption,
                            'username' => $cuenta->email,
                            'password' => $cuenta->password,
                            'timeout' => null,
                        ],
                        'mail.from' => [
                            'address' => $cuenta->email,
                            'name' => $cuenta->alias,
                        ],
                    ]);

                    // Crear el correo
                    $emailData = [
                        'cuerpo' => $solicitud->cuerpo,
                    ];

                    Mail::html($solicitud->cuerpo, function ($message) use ($solicitud) {
                        $message->to($solicitud->destinatario)
                                ->subject($solicitud->asunto)
                                ->from($solicitud->remitente);

                        if ($solicitud->cc) {
                            $message->cc($solicitud->cc);
                        }

                        if ($solicitud->cco) {
                            $message->bcc($solicitud->cco);
                        }

                        if ($solicitud->adjunto) {
                            // Mapear tipos MIME a extensiones para el nombre del archivo
                            $extensionMap = [
                                'application/pdf' => 'pdf',
                                'text/plain' => 'txt',
                                'application/msword' => 'doc',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                                'application/vnd.ms-excel' => 'xls',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                            ];

                            // Determinar el tipo MIME y la extensión
                            $mimeType = $solicitud->adjunto_mime_type ?? 'application/octet-stream';
                            $extension = $extensionMap[$mimeType] ?? 'bin';
                            $fileName = 'Documento.' . $extension; // Nombre en español

                            // Verificar si el adjunto es una cadena Base64
                            if (base64_encode(base64_decode($solicitud->adjunto, true)) === $solicitud->adjunto) {
                                // Es una cadena Base64 válida
                                $fileContent = base64_decode($solicitud->adjunto);
                                if ($fileContent === false) {
                                    Log::error('No se pudo decodificar la cadena Base64 del adjunto', [
                                        'solicitud_id' => $solicitud->id,
                                        'adjunto' => substr($solicitud->adjunto, 0, 100), // Loguear solo los primeros 100 caracteres
                                    ]);
                                    return;
                                }

                                // Adjuntar los datos directamente usando attachData()
                                $message->attachData($fileContent, $fileName, [
                                    'mime' => $mimeType,
                                ]);
                            } elseif (file_exists($solicitud->adjunto)) {
                                // Es una ruta de archivo
                                $message->attach($solicitud->adjunto, [
                                    'as' => $fileName,
                                    'mime' => $mimeType,
                                ]);
                            } else {
                                Log::error('El adjunto no es una cadena Base64 válida ni una ruta de archivo existente', [
                                    'solicitud_id' => $solicitud->id,
                                    'adjunto' => substr($solicitud->adjunto, 0, 100), // Loguear solo los primeros 100 caracteres
                                ]);
                            }
                        }
                    });

                    // Marcar el correo como enviado
                    $solicitud->update([
                        'enviado' => true,
                        'fecha_hora_envio' => now(),
                    ]);

                    Log::info('Correo enviado con éxito', ['solicitud_id' => $solicitud->id]);
                    $this->info('Correo enviado: ' . $solicitud->id);

                } catch (\Exception $e) {
                    Log::error('Error al enviar el correo', [
                        'solicitud_id' => $solicitud->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error('Error al enviar el correo ' . $solicitud->id . ': ' . $e->getMessage());
                }
            }

            return 0;

        } catch (\Exception $e) {
            Log::error('Error general al procesar los correos pendientes', ['error' => $e->getMessage()]);
            $this->error('Error general: ' . $e->getMessage());
            return 1;
        }
    }
}