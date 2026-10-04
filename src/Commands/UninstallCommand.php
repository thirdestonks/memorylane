<?php

namespace Thirdestonks\MemoryLane\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UninstallCommand extends Command
{
    protected $signature = 'memorylane:uninstall {--force : Skip the confirmation}';

    protected $description = 'Drop the MemoryLane table so the package can be removed cleanly';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes all MemoryLane data. Continue?')) {
            return self::FAILURE;
        }

        Schema::connection(config('memorylane.connection'))->dropIfExists('memorylane_requests');

        // Forget the migration too, otherwise a later reinstall thinks the table already exists.
        DB::table(config('database.migrations.table', 'migrations'))
            ->where('migration', 'like', '%_create_memorylane_requests_table')
            ->delete();

        $this->info('MemoryLane table dropped.');
        $this->line('Now run: composer remove thirdestonks/memorylane');

        return self::SUCCESS;
    }
}
