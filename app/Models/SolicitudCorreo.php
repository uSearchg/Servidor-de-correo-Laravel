<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\CuentaCorreo;

class SolicitudCorreo extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_correo';

    protected $fillable = [
        'asunto',
        'cuerpo',
        'remitente',
        'destinatario',
        'cc',
        'cco',
        'adjunto',
        'alias',
        'fecha_hora_recepcion',
        'fecha_hora_envio',
        'enviado',
    ];

    protected $casts = [
        'fecha_hora_recepcion' => 'datetime',
        'fecha_hora_envio' => 'datetime',
        'enviado' => 'boolean',
    ];

    /**
     * Reglas de validación para los datos de una solicitud de correo.
     *
     * @var array
     */
    public static $rules = [
        'asunto' => 'required|string|max:255',
        'cuerpo' => 'required|string',
        'remitente' => 'required|email',
        'destinatario' => 'required|string',
        'alias' => 'required|string|exists:cuentas_correo,alias',
        'cc' => 'nullable|string',
        'cco' => 'nullable|string',
        'adjunto' => 'nullable|string',
    ];

    /**
     * Mensajes de error personalizados para las validaciones.
     *
     * @var array
     */
    public static $messages = [
        'asunto.required' => 'El campo asunto es obligatorio.',
        'asunto.max' => 'El asunto no puede tener más de 255 caracteres.',
        'cuerpo.required' => 'El campo cuerpo es obligatorio.',
        'remitente.required' => 'El campo remitente es obligatorio.',
        'remitente.email' => 'El remitente debe ser un correo válido.',
        'destinatario.required' => 'El campo destinatario es obligatorio.',
        'alias.required' => 'El campo alias es obligatorio.',
        'alias.exists' => 'El alias no existe en las cuentas de correo.',
        'cc.string' => 'El campo CC debe ser una cadena de correos separados por ;.',
        'cco.string' => 'El campo CCO debe ser una cadena de correos separados por ;.',
    ];

    /**
     * Valida los datos de una solicitud de correo.
     *
     * @param  array  $data
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validateData(array $data): array
    {
        // Validar las reglas básicas
        $validator = Validator::make($data, static::$rules, static::$messages);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();

        // Validar que el remitente coincida con el email de la cuenta seleccionada por alias
        $cuenta = CuentaCorreo::where('alias', $validated['alias'])->first();
        if ($cuenta->email !== $validated['remitente']) {
            Log::error('El remitente no coincide con el email de la cuenta', [
                'remitente' => $validated['remitente'],
                'cuenta_email' => $cuenta->email,
            ]);
            throw new \Exception('El remitente debe coincidir con el email de la cuenta seleccionada por alias.');
        }

        // Validar los correos en destinatario, cc y cco
        static::validateEmails($validated['destinatario'], 'destinatario');
        if (isset($validated['cc'])) {
            static::validateEmails($validated['cc'], 'cc');
        }
        if (isset($validated['cco'])) {
            static::validateEmails($validated['cco'], 'cco');
        }

        // Validar el adjunto en base64 (si está presente)
        if (isset($validated['adjunto'])) {
            if (!static::isValidBase64($validated['adjunto'])) {
                Log::error('Adjunto no válido', ['adjunto' => $validated['adjunto']]);
                throw new \Exception('El adjunto debe ser una cadena válida en base64.');
            }
        }

        return $validated;
    }

    /**
     * Valida una cadena de correos separados por ;.
     *
     * @param  string  $emailString
     * @param  string  $field
     * @return void
     * @throws \Exception
     */
    protected static function validateEmails(string $emailString, string $field): void
    {
        $emails = explode(';', $emailString);
        foreach ($emails as $email) {
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("El campo $field contiene un correo inválido: $email");
            }
        }
    }

    /**
     * Valida si una cadena es un base64 válido.
     *
     * @param  string  $data
     * @return bool
     */
    protected static function isValidBase64(string $data): bool
    {
        if (preg_match('/^data:[a-z]+\/[a-z]+;base64,/', $data)) {
            $base64String = substr($data, strpos($data, ',') + 1);
            return base64_decode($base64String, true) !== false;
        }
        return base64_decode($data, true) !== false;
    }
}