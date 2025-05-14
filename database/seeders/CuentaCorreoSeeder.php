<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CuentaCorreo;

class CuentaCorreoSeeder extends Seeder
{
    public function run(): void
    {
        
        $cuentas = [
            [      //CUENTA DE PRUEBA
                'alias' => '', 
                'email' => '',
                'password' => '',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
            ],
            [      //EJEMPLOS:
                'alias' => 'ejemplo', 
                'email' => 'ejemplo@gmail.com', 
                'password' => 'contraseña', 
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
            ],
            [     
                'alias' => 'ejemplo2', 
                'email' => 'ejemplo2@gmail.com', 
                'password' => 'contraseña2', 
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
            ],
        ];

        foreach ($cuentas as $cuenta) {
            CuentaCorreo::firstOrCreate( //EVITAMOS DUPLICADOS
                ['alias' => $cuenta['alias']], 
                [ 
                    'email' => $cuenta['email'],
                    'password' => $cuenta['password'],
                    'host' => $cuenta['host'],
                    'port' => $cuenta['port'],
                    'encryption' => $cuenta['encryption'],
                ]
            );
        }
    }
}