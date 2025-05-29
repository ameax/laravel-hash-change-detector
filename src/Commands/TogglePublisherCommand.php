<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Console\Command;

class TogglePublisherCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:publisher:toggle 
                            {id : The publisher ID}
                            {--activate : Activate the publisher}
                            {--deactivate : Deactivate the publisher}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle a publisher active/inactive status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $publisher = Publisher::find($id);

        if (! $publisher) {
            $this->error("Publisher with ID {$id} not found.");

            return self::FAILURE;
        }

        // Determine new status
        if ($this->option('activate')) {
            $newStatus = 'active';
        } elseif ($this->option('deactivate')) {
            $newStatus = 'inactive';
        } else {
            // Toggle current status
            $newStatus = $publisher->status === 'active' ? 'inactive' : 'active';
        }

        $publisher->update(['status' => $newStatus]);

        $this->info("Publisher '{$publisher->name}' is now {$newStatus}.");

        return self::SUCCESS;
    }
}
