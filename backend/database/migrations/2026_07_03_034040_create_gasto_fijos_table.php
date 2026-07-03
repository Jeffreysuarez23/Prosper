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
        Schema::create('gasto_fijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nombre', 100);
            $table->decimal('monto', 12, 2);
            $table->decimal('monto_pagado_mes', 12, 2)->default(0.00);
            $table->integer('dia_vencimiento')->default(1);
            $table->string('icono', 50)->default('🏠');
            $table->date('fecha_ultimo_pago')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gasto_fijos');
    }
};
