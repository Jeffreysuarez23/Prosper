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
        Schema::table('objetivos', function (Blueprint $table) {
            $table->tinyInteger('dia_recordatorio')->unsigned()->nullable()->after('fecha_limite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('objetivos', function (Blueprint $table) {
            $table->dropColumn('dia_recordatorio');
        });
    }
};
