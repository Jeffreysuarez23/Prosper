<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_tarjeta_creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarjeta_credito_id')->constrained('tarjeta_creditos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('compra_tarjeta_id')->nullable()->constrained('compra_tarjeta_creditos')->onDelete('cascade');
            $table->enum('tipo', ['compra', 'abono']);
            $table->decimal('monto', 20, 2);
            $table->string('descripcion');
            $table->date('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_tarjeta_creditos');
    }
};
