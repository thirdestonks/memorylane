<?php

namespace Thirdestonks\MemoryLane\Commands;

use Illuminate\Console\Command;
use Thirdestonks\MemoryLane\Models\Lane;

class PruneCommand extends Command
{
    protected $signature = 'memorylane:prune {--hours= : Keep lanes newer than this (default: keep_hours config)}';

    protected $description = 'Delete old MemoryLane lanes';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('memorylane.keep_hours'));

        $deleted = Lane::olderThan($hours)->delete();

        $this->info("Pruned {$deleted} lane(s) older than {$hours}h.");

        return self::SUCCESS;
    }
}
