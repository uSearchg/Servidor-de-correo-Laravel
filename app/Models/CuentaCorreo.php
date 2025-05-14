<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaCorreo extends Model
{
    protected $table = 'cuentas_correo'; 

    protected $fillable = ['alias', 'email', 'password', 'host', 'port', 'encryption'];
}