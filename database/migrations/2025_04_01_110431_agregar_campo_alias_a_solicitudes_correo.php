<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecutar la migración.
     */
    public function up(): void
    {
        Schema::table('solicitudes_correo', function (Blueprint $table) {
            $table->string('alias')->after('destinatario'); 
        });
    }

    /**
     * Revertir la migración.
     */
    public function down(): void
    {
        Schema::table('solicitudes_correo', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};