<?php

namespace Thirdestonks\MemoryLane;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Thirdestonks\MemoryLane\Commands\PruneCommand;
use Thirdestonks\MemoryLane\Commands\UninstallCommand;
use Thirdestonks\MemoryLane\Http\Controllers\DashboardController;
use Thirdestonks\MemoryLane\Http\Controllers\LoginController;
use Thirdestonks\MemoryLane\Http\Middleware\Authenticate;
use Thirdestonks\MemoryLane\Http\Middleware\RecordLane;
use Throwable;

class MemoryLaneServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('memorylane')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommands([PruneCommand::class, UninstallCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Recorder::class);
    }

    public function packageBooted(): void
    {
        // Picked up by the host's normal `php artisan migrate`; nothing to publish.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! config('memorylane.enabled')) {
            return;
        }

        // No default gate: every environment, local included, needs the login. A host may still
        // Gate::define('viewMemoryLane', ...) to let its own users in.

        $recorder = $this->app->make(Recorder::class);

        DB::listen(function (QueryExecuted $query) use ($recorder) {
            try {
                $recorder->recordQuery($query);
            } catch (Throwable $e) {
                $recorder->reportOnce($e);
            }
        });

        // Registered once: terminating() callbacks pile up on a reused app, so one hook saves whatever is queued.
        $this->app->terminating(fn () => $recorder->save());

        // Prepend = outermost middleware, so the timing wraps the whole request.
        $this->app->make(Kernel::class)->prependMiddleware(RecordLane::class);

        Route::prefix(config('memorylane.path'))
            ->middleware(config('memorylane.middleware'))
            ->name('memorylane.')
            ->group(function () {
                Route::get('/login', [LoginController::class, 'show'])->name('login');
                Route::post('/login', [LoginController::class, 'login'])->middleware(ThrottleRequests::class.':5,1');
                Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

                Route::middleware(Authenticate::class)->group(function () {
                    Route::get('/', [DashboardController::class, 'index'])->name('index');
                    Route::get('/{lane}', [DashboardController::class, 'show'])->whereNumber('lane')->name('show');
                    Route::post('/prune', [DashboardController::class, 'prune'])->name('prune');
                    Route::post('/clear', [DashboardController::class, 'clear'])->name('clear');
                });
            });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('memorylane:prune')->hourly();
        });
    }
}
