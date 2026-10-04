<?php

namespace Thirdestonks\MemoryLane\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Thirdestonks\MemoryLane\Recorder;
use Throwable;

class RecordLane
{
    public function __construct(private Recorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = $this->begin($request);

        // Deliberately outside any try/catch: the host's own exceptions are none of our business.
        $response = $next($request);

        if ($start !== null) {
            $this->finish($request, $response, $start);
        }

        return $response;
    }

    private function begin(Request $request): ?int
    {
        try {
            if ($request->is(trim(config('memorylane.path'), '/').'*', ...config('memorylane.ignore'))) {
                return null;
            }

            // Under Octane the process lives across requests, so reset or the peak leaks between them.
            memory_reset_peak_usage();
            $this->recorder->start();

            return hrtime(true);
        } catch (Throwable $e) {
            $this->recorder->reportOnce($e);

            return null;
        }
    }

    private function finish(Request $request, Response $response, int $start): void
    {
        try {
            $durationMs = round((hrtime(true) - $start) / 1e6, 2);
            $captured = $this->recorder->stop();

            // Queries are buffered for every request because "slow" is only known now, at the end.
            $keep = $durationMs >= config('memorylane.always_record_slow')
                || Lottery::odds((float) config('memorylane.sample_rate'))->choose();

            if (! $keep) {
                return;
            }

            $route = $request->route()?->uri();

            // Saved by the provider's single terminating() hook, after the response is sent.
            $this->recorder->queue([
                'method' => $request->method(),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 500, ''),
                'route' => $route === null ? null : Str::limit('/'.ltrim($route, '/'), 255, ''),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
                'query_count' => count($captured['queries']) + $captured['dropped'],
                'query_time_ms' => round(array_sum(array_column($captured['queries'], 'time_ms')), 2),
                'n_plus_one_count' => count($captured['n_plus_one']),
                'payload' => [
                    'queries' => $captured['queries'],
                    'n_plus_one' => $captured['n_plus_one'],
                    'dropped_queries' => $captured['dropped'],
                ],
            ]);
        } catch (Throwable $e) {
            $this->recorder->reportOnce($e);
        }
    }
}
