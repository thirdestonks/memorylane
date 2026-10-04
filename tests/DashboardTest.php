<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Thirdestonks\MemoryLane\Models\Lane;

it('is locked by default', function () {
    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('opens when the host grants the viewMemoryLane gate', function () {
    allowDashboard();

    $this->get('/memorylane')->assertOk()->assertSee('No requests recorded yet')->assertSee('data:image/png;base64,', false);
});

it('lists lanes and shows a lane with its N+1', function () {
    config(['memorylane.n_plus_one_threshold' => 3]);
    Route::get('/orders', function () {
        foreach (range(1, 4) as $i) {
            DB::select('select ? as n', [$i]);
        }

        return 'ok';
    });
    $this->get('/orders');
    allowDashboard();

    $lane = Lane::sole();

    $this->get('/memorylane')->assertOk()->assertSee('/orders')->assertSee('N+1');
    $this->get('/memorylane?view=n1')->assertOk()->assertSee('/orders');
    $this->get("/memorylane/{$lane->id}")->assertOk()->assertSee('select ? as n')->assertSee('×4');
});

it('filters the list to one endpoint when it is clicked', function () {
    Route::get('/a/{id}', fn () => 'a');
    Route::get('/b', fn () => 'b');
    $this->get('/a/1');
    $this->get('/a/2');
    $this->get('/b');
    allowDashboard();

    $this->get('/memorylane?route=/a/{id}&method=GET')->assertOk()
        ->assertViewHas('lanes', fn ($lanes) => $lanes->count() === 2 && $lanes->every(fn ($l) => $l->route === '/a/{id}'));
});

it('filters the slow view by the always_record_slow threshold', function () {
    Route::get('/fast', fn () => 'ok');
    $this->get('/fast');
    allowDashboard();

    config(['memorylane.always_record_slow' => 100000]);

    $this->get('/memorylane?view=slow')->assertOk()->assertViewHas('lanes', fn ($lanes) => $lanes->isEmpty());
});
