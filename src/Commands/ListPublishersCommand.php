<?php

declare(strict_types=1);

namespace ameax\HashChangeDetector\Commands;

use ameax\HashChangeDetector\Models\Publisher;
use Illuminate\Console\Command;

class ListPublishersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hash-detector:publisher:list 
                            {--model= : Filter by model class}
                            {--status= : Filter by status (active/inactive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all configured publishers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Publisher::query();

        if ($model = $this->option('model')) {
            $query->where('model_type', $model);
        }

        if ($status = $this->option('status')) {
            if (! in_array($status, ['active', 'inactive'])) {
                $this->error("Invalid status. Use 'active' or 'inactive'.");

                return self::FAILURE;
            }
            $query->where('status', $status);
        }

        $publishers = $query->get();

        if ($publishers->isEmpty()) {
            $this->info('No publishers found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Model', 'Publisher', 'Status', 'Deletion', 'Created At'],
            $publishers->map(fn ($p) => [
                $p->id,
                $p->name,
                class_basename($p->model_type),
                class_basename($p->publisher_class),
                $p->status,
                $p->isDeletePublisher() ? '<info>Yes</info>' : 'No',
                $p->created_at->format('Y-m-d H:i:s'),
            ])
        );

        return self::SUCCESS;
    }
}
