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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('tipo', ['urgent', 'warning', 'info', 'success']);
            $table->string('icono', 10)->nullable();
            $table->string('titulo', 255);
            $table->text('mensaje');
            $table->string('categoria', 50)->nullable();
            $table->boolean('leida')->default(false);
            $table->string('accion_texto', 50)->nullable();
            $table->string('accion_url', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
