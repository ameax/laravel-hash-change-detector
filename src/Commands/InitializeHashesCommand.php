<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Contracts\Hashable;
use Illuminate\Console\Command;

class InitializeHashesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:initialize-hashes 
                            {model : The model class to initialize hashes for}
                            {--chunk=100 : Number of records to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize hashes for models that don\'t have them (useful for read-only models)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $chunkSize = (int) $this->option('chunk');

        if (! class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist.");

            return self::FAILURE;
        }

        $model = new $modelClass;

        if (! $model instanceof Hashable) {
            $this->error("Model {$modelClass} must implement Hashable interface.");

            return self::FAILURE;
        }

        $this->info("Initializing hashes for {$modelClass}...");

        $total = $modelClass::count();
        $processed = 0;
        $initialized = 0;

        $modelClass::chunk($chunkSize, function ($models) use (&$processed, &$initialized, $total) {
            foreach ($models as $model) {
                if (! $model->getCurrentHash()) {
                    if (method_exists($model, 'initializeHash')) {
                        $model->initializeHash();
                    } else {
                        $model->updateHash();
                    }
                    $initialized++;
                }
                $processed++;

                if ($processed % 100 === 0) {
                    $this->info("Progress: {$processed}/{$total} processed, {$initialized} initialized");
                }
            }
        });

        $this->info("Completed: {$processed} records processed, {$initialized} hashes initialized.");

        return self::SUCCESS;
    }
}
