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
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('tipo', ['ingreso', 'gasto', 'ahorro']);
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('categoria', 50);
            $table->string('descripcion', 255)->nullable();
            $table->string('metodo_pago', 50)->nullable();
            $table->timestamps();

            $table->index('fecha');
            $table->index(['user_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
