<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CleanupGuestUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-guests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete guest users who have not been active for more than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting guest cleanup...');

        $count = User::where('role', 'guest')
            ->where('created_at', '<', Carbon::now()->subDay())
            ->delete();

        $this->info("Successfully deleted $count inactive guest accounts.");
    }
}
