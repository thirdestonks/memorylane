<?php

use Thirdestonks\MemoryLane\Models\Lane;

function storeLane(int $hoursAgo): Lane
{
    $lane = Lane::create(['method' => 'GET', 'path' => '/x', 'status' => 200, 'duration_ms' => 1, 'peak_memory_mb' => 1, 'payload' => []]);
    $lane->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();

    return $lane;
}

beforeEach(fn () => config(['memorylane.keep_hours' => 72]));

it('prunes only lanes older than keep_hours from the dashboard', function () {
    storeLane(80);
    $fresh = storeLane(1);
    allowDashboard();

    $this->post('/memorylane/prune')->assertRedirect('/memorylane')->assertSessionHas('memorylane.status', 'Pruned 1 lane(s) older than 72h.');

    expect(Lane::pluck('id')->all())->toBe([$fresh->id]);
});

it('clears every lane but keeps the table when "clear" is typed', function () {
    storeLane(80);
    storeLane(1);
    allowDashboard();

    $this->post('/memorylane/clear', ['confirm' => 'clear'])->assertRedirect('/memorylane');

    expect(Lane::count())->toBe(0);
    $this->get('/memorylane')->assertOk()->assertSee('Cleared all 2 lane(s).');
});

it('refuses to clear without the typed confirmation, even from a hand-built request', function () {
    storeLane(1);
    allowDashboard();

    $this->post('/memorylane/clear')->assertRedirect('/memorylane');
    $this->post('/memorylane/clear', ['confirm' => 'yes'])->assertRedirect('/memorylane');

    expect(Lane::count())->toBe(1);
});

it('locks housekeeping behind the same login as the dashboard', function () {
    config(['memorylane.username' => 'ops', 'memorylane.password' => 'key']);
    storeLane(80);

    $this->post('/memorylane/prune')->assertRedirect('/memorylane/login');
    $this->post('/memorylane/clear', ['confirm' => 'clear'])->assertRedirect('/memorylane/login');

    expect(Lane::count())->toBe(1);
});

it('does nothing without a login, even with no credentials configured', function () {
    storeLane(1);

    $this->post('/memorylane/clear', ['confirm' => 'clear'])->assertRedirect('/memorylane/login');

    expect(Lane::count())->toBe(1);
});
