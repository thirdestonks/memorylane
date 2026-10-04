<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Thirdestonks\MemoryLane\Models\Lane;

function makeLane(array $attributes = []): Lane
{
    return Lane::create([
        'method' => 'GET', 'path' => '/x', 'status' => 200, 'duration_ms' => 1,
        'peak_memory_mb' => 1, 'payload' => [], ...$attributes,
    ]);
}

it('prunes lanes older than keep_hours', function () {
    config(['memorylane.keep_hours' => 72]);
    $old = makeLane();
    $old->forceFill(['created_at' => now()->subHours(73)])->save();
    $fresh = makeLane();

    $this->artisan('memorylane:prune')->assertSuccessful();

    expect(Lane::pluck('id')->all())->toBe([$fresh->id]);
});

it('schedules the prune hourly on its own', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($event) => str_contains($event->command, 'memorylane:prune') && $event->expression === '0 * * * *'))->toBeTrue();
});

it('uninstalls cleanly: drops the table and forgets the migration', function () {
    expect(Schema::hasTable('memorylane_requests'))->toBeTrue();
    expect(DB::table('migrations')->where('migration', 'like', '%create_memorylane_requests_table')->exists())->toBeTrue();

    $this->artisan('memorylane:uninstall', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('memorylane_requests'))->toBeFalse();
    expect(DB::table('migrations')->where('migration', 'like', '%create_memorylane_requests_table')->exists())->toBeFalse();
});

it('keeps serving the host after uninstall', function () {
    $this->artisan('memorylane:uninstall', ['--force' => true]);
    Route::get('/hello', fn () => 'hi');

    $this->get('/hello')->assertOk()->assertSee('hi');
});
