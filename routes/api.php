<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolicitudCorreoController;

Route::post('/enviar-correo', [SolicitudCorreoController::class, 'almacenar'])->middleware('auth.token');