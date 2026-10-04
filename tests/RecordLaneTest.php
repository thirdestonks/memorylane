<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Lottery;
use Thirdestonks\MemoryLane\Models\Lane;
use Thirdestonks\MemoryLane\Recorder;

afterEach(fn () => Lottery::determineResultsNormally());

it('saves one lane per request with timing, memory and queries', function () {
    Route::get('/users/{id}', function () {
        DB::select('select 1 as one');
        DB::select('select 2 as two');

        return 'ok';
    });

    $this->get('/users/5')->assertOk();

    $lane = Lane::sole();
    expect($lane->method)->toBe('GET')
        ->and($lane->path)->toBe('/users/5')
        ->and($lane->route)->toBe('/users/{id}')
        ->and($lane->status)->toBe(200)
        ->and($lane->duration_ms)->toBeFloat()
        ->and($lane->peak_memory_mb)->toBeGreaterThan(0)
        ->and($lane->query_count)->toBe(2)
        ->and($lane->n_plus_one_count)->toBe(0)
        ->and(array_column($lane->payload['queries'], 'sql'))->toBe(['select 1 as one', 'select 2 as two']);
});

it('flags an N+1 when the same query shape repeats past the threshold', function () {
    config(['memorylane.n_plus_one_threshold' => 10]);
    Route::get('/posts', function () {
        foreach (range(1, 12) as $id) {
            DB::select('select * from sqlite_master where rootpage = ?', [$id]);
        }
        DB::select('select 1');

        return 'ok';
    });

    $this->get('/posts')->assertOk();

    $lane = Lane::sole();
    expect($lane->n_plus_one_count)->toBe(1)
        ->and($lane->payload['n_plus_one'][0]['count'])->toBe(12)
        ->and($lane->payload['n_plus_one'][0]['sql'])->toBe('select * from sqlite_master where rootpage = ?');
})->skip(fn () => DB::getDriverName() !== 'sqlite', 'query targets sqlite_master');

it('does not flag repeats below the threshold', function () {
    config(['memorylane.n_plus_one_threshold' => 10]);
    Route::get('/few', function () {
        foreach (range(1, 9) as $i) {
            DB::select('select ? as n', [$i]);
        }

        return 'ok';
    });

    $this->get('/few')->assertOk();

    expect(Lane::sole()->n_plus_one_count)->toBe(0);
});

it('never stores binding values by default', function () {
    Route::get('/secret', fn () => DB::select('select ? as email', ['jane@example.com']) ? 'ok' : 'ok');

    $this->get('/secret')->assertOk();

    expect(Lane::sole()->getRawOriginal('payload'))->not->toContain('jane@example.com');
});

it('stores bindings only when capture_bindings is on', function () {
    config(['memorylane.capture_bindings' => true]);
    Route::get('/secret', fn () => DB::select('select ? as email', ['jane@example.com']) ? 'ok' : 'ok');

    $this->get('/secret')->assertOk();

    expect(Lane::sole()->payload['queries'][0]['bindings'])->toBe(['jane@example.com']);
});

it('stops buffering past max_queries but still counts them', function () {
    config(['memorylane.max_queries' => 3]);
    Route::get('/loop', function () {
        foreach (range(1, 5) as $i) {
            DB::select('select ? as n', [$i]);
        }

        return 'ok';
    });

    $this->get('/loop')->assertOk();

    $lane = Lane::sole();
    expect($lane->payload['queries'])->toHaveCount(3)
        ->and($lane->payload['dropped_queries'])->toBe(2)
        ->and($lane->query_count)->toBe(5);
});

it('skips unsampled requests', function () {
    Lottery::alwaysLose();
    Route::get('/hello', fn () => 'hi');

    $this->get('/hello')->assertOk();

    expect(Lane::count())->toBe(0);
});

it('always records slow requests even when not sampled', function () {
    Lottery::alwaysLose();
    config(['memorylane.always_record_slow' => 0]);
    Route::get('/hello', fn () => 'hi');

    $this->get('/hello')->assertOk();

    expect(Lane::count())->toBe(1);
});

it('ignores configured paths and the dashboard itself', function () {
    Route::get('/up', fn () => 'up');
    allowDashboard();

    $this->get('/up')->assertOk();
    $this->get('/memorylane')->assertOk();

    expect(Lane::count())->toBe(0);
});

it('records failing requests too', function () {
    Route::get('/boom', fn () => abort(500));

    $this->get('/boom')->assertStatus(500);

    expect(Lane::sole()->status)->toBe(500);
});

it('does not record its own insert as a query', function () {
    Route::get('/hello', fn () => 'hi');

    $this->get('/hello');
    $this->get('/hello');

    expect(Lane::pluck('query_count')->all())->toBe([0, 0]);
});

it('never breaks the host when saving fails', function () {
    Schema::drop('memorylane_requests');
    Route::get('/hello', fn () => 'hi');
    Log::spy();

    $this->get('/hello')->assertOk()->assertSee('hi');
    $this->get('/hello')->assertOk();

    Log::shouldHaveReceived('warning')->once();
});

it('warns once across processes, not once per request', function () {
    Log::spy();
    $e = new RuntimeException("Table 'memorylane_requests' doesn't exist");

    // Two Recorders = two PHP-FPM processes. Only the cache is shared between them.
    (new Recorder)->reportOnce($e);
    (new Recorder)->reportOnce($e);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message) => str_contains($message, 'has the migration run?'));
});

it('writes nothing to laravel.log when things work', function () {
    Route::get('/hello', fn () => 'hi');
    Log::spy();

    $this->get('/hello')->assertOk();

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
});

it('registers nothing when MEMORYLANE_ENABLED=false', function () {
    putenv('MEMORYLANE_ENABLED=false');
    $_ENV['MEMORYLANE_ENABLED'] = $_SERVER['MEMORYLANE_ENABLED'] = 'false';

    try {
        $this->refreshApplication();
        Route::get('/hello', fn () => 'hi');

        $this->get('/hello')->assertOk();
        $this->get('/memorylane')->assertNotFound();
        expect(app('router')->has('memorylane.index'))->toBeFalse();
    } finally {
        putenv('MEMORYLANE_ENABLED');
        unset($_ENV['MEMORYLANE_ENABLED'], $_SERVER['MEMORYLANE_ENABLED']);
    }
});
