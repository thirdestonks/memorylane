<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Thirdestonks\MemoryLane\Models\Lane;

it('grades by duration, Lighthouse style', function () {
    expect(Lane::gradeFor(50))->toBe('A')
        ->and(Lane::gradeFor(150))->toBe('B')
        ->and(Lane::gradeFor(450))->toBe('C')
        ->and(Lane::gradeFor(800))->toBe('D')
        ->and(Lane::gradeFor(5000))->toBe('F');
});

it('drops one letter for an N+1, never below F', function () {
    expect(Lane::gradeFor(50, 1))->toBe('B')
        ->and(Lane::gradeFor(5000, 3))->toBe('F');
});

it('records the app file:line that ran each query', function () {
    Route::get('/src', function () {
        DB::select('select 1');

        return 'ok';
    });

    $this->get('/src')->assertOk();

    expect(Lane::sole()->payload['queries'][0]['source'])->toContain('InsightsTest.php:');
});

it('skips source capture when turned off', function () {
    config(['memorylane.capture_source' => false]);
    Route::get('/src', fn () => DB::select('select 1') ? 'ok' : 'ok');

    $this->get('/src')->assertOk();

    expect(Lane::sole()->payload['queries'][0])->not->toHaveKey('source');
});

it('ranks endpoints by impact (hits × average), not average alone', function () {
    $make = fn ($route, $ms) => Lane::create(['method' => 'GET', 'path' => $route, 'route' => $route, 'status' => 200, 'duration_ms' => $ms, 'peak_memory_mb' => 1, 'payload' => []]);
    $make('/rare-slow', 800);
    foreach (range(1, 10) as $i) {
        $make('/busy', 200);
    }
    allowDashboard();

    $this->get('/memorylane')->assertOk()->assertViewHas('endpoints', fn ($e) => $e->first()->route === '/busy');
});

it('marks slow queries on the lane page', function () {
    config(['memorylane.slow_query_ms' => 5]);
    $lane = Lane::create(['method' => 'GET', 'path' => '/x', 'status' => 200, 'duration_ms' => 10, 'peak_memory_mb' => 1,
        'payload' => ['queries' => [['sql' => 'select heavy', 'time_ms' => 9.5], ['sql' => 'select light', 'time_ms' => 0.2]], 'n_plus_one' => [], 'dropped_queries' => 0]]);
    allowDashboard();

    $this->get("/memorylane/{$lane->id}")->assertOk()->assertSee('1 slow (≥ 5 ms)', false)->assertSee('Slow');
});
