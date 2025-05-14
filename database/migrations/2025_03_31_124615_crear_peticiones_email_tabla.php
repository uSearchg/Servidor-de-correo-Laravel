<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('solicitudes_correo', function (Blueprint $table) {
            $table->id();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->string('remitente');
            $table->string('cc')->nullable();
            $table->string('cco')->nullable();
            $table->string('destinatario');
            $table->text('adjunto')->nullable();
            $table->timestamp('fecha_hora_recepcion')->useCurrent();
            $table->timestamp('fecha_hora_envio')->nullable();
            $table->boolean('enviado')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_correo');
    }
};
