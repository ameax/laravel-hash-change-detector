<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Console\Command;

class CreatePublisherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:publisher:create 
                            {name : The name of the publisher}
                            {model : The model class to publish}
                            {publisher : The publisher class to use}
                            {--inactive : Create the publisher as inactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new publisher configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $modelClass = $this->argument('model');
        $publisherClass = $this->argument('publisher');

        // Validate model class exists
        if (! class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist.");

            return self::FAILURE;
        }

        // Validate publisher class exists
        if (! class_exists($publisherClass)) {
            $this->error("Publisher class {$publisherClass} does not exist.");

            return self::FAILURE;
        }

        // Check if publisher already exists
        $exists = Publisher::where('name', $name)
            ->where('model_type', $modelClass)
            ->exists();

        if ($exists) {
            $this->error("Publisher '{$name}' for model {$modelClass} already exists.");

            return self::FAILURE;
        }

        // Create the publisher
        $publisher = Publisher::create([
            'name' => $name,
            'model_type' => $modelClass,
            'publisher_class' => $publisherClass,
            'status' => $this->option('inactive') ? 'inactive' : 'active',
        ]);

        $this->info("Publisher '{$name}' created successfully.");
        $this->table(
            ['ID', 'Name', 'Model', 'Publisher', 'Status'],
            [[$publisher->id, $publisher->name, $publisher->model_type, $publisher->publisher_class, $publisher->status]]
        );

        return self::SUCCESS;
    }
}
