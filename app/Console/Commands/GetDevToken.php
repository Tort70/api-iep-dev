<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GetDevToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:token {--regenerate : Regenerate the dev token}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get or regenerate the dev user Bearer token';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!app()->environment(['local', 'development'])) {
            $this->error('This command can only be run in local/development environments.');
            return Command::FAILURE;
        }

        $devUser = User::where('email', 'dev@orvis.local')->first();

        if (!$devUser) {
            $this->error('Dev user not found. Run: php artisan db:seed');
            return Command::FAILURE;
        }

        if ($this->option('regenerate')) {
            $devUser->tokens()->delete();
            $token = $devUser->createToken('dev-token')->plainTextToken;
            $this->info('Dev token regenerated!');
        } else {
            $existingToken = $devUser->tokens()->where('name', 'dev-token')->first();

            if (!$existingToken) {
                $token = $devUser->createToken('dev-token')->plainTextToken;
                $this->info('New dev token created!');
            } else {
                $this->warn('Cannot display existing token (tokens are hashed).');
                $this->warn('Use --regenerate to create a new token.');
                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('==============================================');
        $this->info('DEV BEARER TOKEN:');
        $this->info($token);
        $this->info('==============================================');
        $this->info('Add to your React fetch headers:');
        $this->info('Authorization: Bearer ' . $token);
        $this->info('==============================================');

        return Command::SUCCESS;
    }
}
