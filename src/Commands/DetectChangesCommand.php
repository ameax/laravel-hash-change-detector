<?php

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Jobs\DetectChangesJob;
use Illuminate\Console\Command;

class DetectChangesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:detect-changes {model? : The model class to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect changes in models using MySQL hash calculation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');
        
        if ($modelClass) {
            $this->info("Detecting changes for {$modelClass}...");
        } else {
            $this->info('Detecting changes for all hashable models...');
        }
        
        DetectChangesJob::dispatch($modelClass);
        
        $this->info('Change detection job dispatched.');
        
        return self::SUCCESS;
    }
}