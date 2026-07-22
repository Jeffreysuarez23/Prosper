<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_tarjeta_creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarjeta_credito_id')->constrained('tarjeta_creditos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('descripcion');
            $table->decimal('monto', 20, 2);
            $table->decimal('monto_pagado', 20, 2)->default(0);
            $table->enum('estado', ['pendiente', 'pagado'])->default('pendiente');
            $table->date('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_tarjeta_creditos');
    }
};
