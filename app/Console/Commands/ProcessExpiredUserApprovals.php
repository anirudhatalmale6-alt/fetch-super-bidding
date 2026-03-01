<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Interstate\UserApprovalTimeoutService;

class ProcessExpiredUserApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interstate:process-expired-approvals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process interstate delivery requests where user approval deadline has expired';

    /**
     * Execute the console command.
     */
    public function handle(UserApprovalTimeoutService $timeoutService): int
    {
        $this->info('Processing expired user approvals...');
        
        $timeoutService->processExpiredApprovals();
        
        $this->info('Processing approval reminders...');
        
        $timeoutService->sendApprovalReminders();
        
        $this->info('Done!');
        
        return Command::SUCCESS;
    }
}
