<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Membresia;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckExpiredMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membresias:check-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa si hay membresías expiradas y actualiza su estado en la base de datos a expired y plan gratis.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredCount = Membresia::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', Carbon::now())
            ->update([
                'status' => 'expired',
                'plan' => 'gratis'
            ]);

        $this->info("Se han actualizado {$expiredCount} membresías expiradas a gratis/expired.");
        Log::info("Se han actualizado {$expiredCount} membresías expiradas a gratis/expired.");
    }
}
