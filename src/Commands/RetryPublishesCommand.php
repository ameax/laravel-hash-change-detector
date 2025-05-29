<?php

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Jobs\PublishModelJob;
use ameax\HashChangeDetector\Models\Publish;
use Illuminate\Console\Command;

class RetryPublishesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:retry-publishes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry deferred publish attempts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publishes = Publish::pendingOrDeferred()->get();
        
        if ($publishes->isEmpty()) {
            $this->info('No publishes to retry.');
            return self::SUCCESS;
        }
        
        $count = 0;
        foreach ($publishes as $publish) {
            PublishModelJob::dispatch($publish);
            $count++;
        }
        
        $this->info("Dispatched {$count} publish jobs.");
        
        return self::SUCCESS;
    }
}