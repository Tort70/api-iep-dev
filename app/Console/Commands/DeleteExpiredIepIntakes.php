<?php

namespace App\Console\Commands;

use App\Models\IepIntake;
use Illuminate\Console\Command;

class DeleteExpiredIepIntakes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iep:delete-expired {--dry-run : Display what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete IEP intakes that have passed their auto_delete_at timestamp';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no records will be deleted');
        }

        // Find all intakes where auto_delete_at has passed
        $expiredIntakes = IepIntake::where('auto_delete_at', '<=', now())
            ->whereNotNull('auto_delete_at')
            ->get();

        if ($expiredIntakes->isEmpty()) {
            $this->info('No expired IEP intakes found.');
            return Command::SUCCESS;
        }

        $count = $expiredIntakes->count();
        $this->info("Found {$count} expired IEP intake(s):");

        // Display details of what will be/was deleted
        foreach ($expiredIntakes as $intake) {
            $status = $intake->status;
            $expiredAt = $intake->auto_delete_at->diffForHumans();
            $this->line("  - ID: {$intake->id}, Status: {$status}, Expired: {$expiredAt}");
        }

        if ($isDryRun) {
            $this->warn('DRY RUN: No records were deleted');
            return Command::SUCCESS;
        }

        // Confirm deletion in production
        if ($this->laravel->environment('production')) {
            if (!$this->confirm("Delete {$count} expired intake(s)?")) {
                $this->warn('Deletion cancelled');
                return Command::FAILURE;
            }
        }

        // Delete expired intakes
        $deleted = IepIntake::where('auto_delete_at', '<=', now())
            ->whereNotNull('auto_delete_at')
            ->delete();

        $this->info("Successfully deleted {$deleted} expired IEP intake(s)");

        return Command::SUCCESS;
    }
}
