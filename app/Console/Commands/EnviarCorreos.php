<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SolicitudCorreo;
use Illuminate\Support\Facades\Mail;

class EnviarCorreos extends Command
{
    protected $signature = 'correos:enviar';
    protected $description = 'Envía correos pendientes en la cola';

    public function handle()
    {
        $solicitudes = SolicitudCorreo::where('enviado', false)->get();

        foreach ($solicitudes as $solicitud) {
            Mail::html($solicitud->cuerpo, function ($message) use ($solicitud) {
                $message->to($solicitud->destinatario)
                        ->from($solicitud->remitente)
                        ->subject($solicitud->asunto);
            });

            $solicitud->update(['enviado' => true]);
            $this->info("Correo enviado a {$solicitud->destinatario}");
        }

        if ($solicitudes->isEmpty()) {
            $this->info('No hay correos pendientes');
        }
    }
}