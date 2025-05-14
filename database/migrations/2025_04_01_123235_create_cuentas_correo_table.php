<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_correo', function (Blueprint $table) {
            $table->id();
            $table->string('alias', 50)->unique();
            $table->string('email');
            $table->string('password');
            $table->string('host')->default('smtp.gmail.com');
            $table->integer('port')->default(587);
            $table->string('encryption')->default('tls');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_correo');
    }
};