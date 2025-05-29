<?php

namespace ameax\HashChangeDetector\Commands;

use Illuminate\Console\Command;

class HashChangeDetectorCommand extends Command
{
    public $signature = 'laravel-hash-change-detector';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
