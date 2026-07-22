<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_gastos_fijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gasto_fijo_id')->constrained('gasto_fijos')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['abono', 'retiro']);
            $table->decimal('monto', 20, 2);
            $table->date('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_gastos_fijos');
    }
};
