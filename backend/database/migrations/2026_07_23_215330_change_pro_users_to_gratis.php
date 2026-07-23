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
        \Illuminate\Support\Facades\DB::table('membresias')
            ->where('plan', 'pro')
            ->update(['plan' => 'gratis']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No way to reverse automatically without history
    }
};
