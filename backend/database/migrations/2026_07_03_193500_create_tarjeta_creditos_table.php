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
        Schema::create('tarjeta_creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nombre', 100);
            $table->string('banco', 100)->nullable();
            $table->string('ultimos_digitos', 4)->nullable();
            $table->decimal('limite_credito', 12, 2)->default(0);
            $table->decimal('deuda_actual', 12, 2)->default(0);
            $table->integer('dia_corte')->default(1);
            $table->integer('dia_pago')->default(15);
            $table->decimal('tasa_interes', 5, 2)->default(0);
            $table->string('icono', 50)->default('💳');
            $table->string('color', 7)->default('#3b82f6');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarjeta_creditos');
    }
};
