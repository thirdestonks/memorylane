<?php

namespace Thirdestonks\MemoryLane;

use DateTimeInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Thirdestonks\MemoryLane\Models\Lane;
use Throwable;

// One per app (singleton): the DB::listen hook and the middleware share this buffer.
class Recorder
{
    private bool $active = false;

    private array $queries = [];

    private int $dropped = 0;

    private int $maxQueries = 0;

    private bool $captureBindings = false;

    private bool $captureSource = false;

    private bool $failureLogged = false;

    private ?array $pending = null;

    public function start(): void
    {
        // Reset everything: under Octane this object survives between requests.
        $this->active = true;
        $this->queries = [];
        $this->dropped = 0;
        $this->maxQueries = (int) config('memorylane.max_queries');
        $this->captureBindings = (bool) config('memorylane.capture_bindings');
        $this->captureSource = (bool) config('memorylane.capture_source');
    }

    public function recordQuery(QueryExecuted $query): void
    {
        if (! $this->active) {
            return;
        }

        if (count($this->queries) >= $this->maxQueries) {
            $this->dropped++;

            return;
        }

        $entry = [
            'sql' => $query->sql,
            'time_ms' => round($query->time, 2),
            'connection' => $query->connectionName,
        ];

        if ($this->captureBindings) {
            $entry['bindings'] = array_map($this->bindingForStorage(...), $query->bindings);
        }

        if ($this->captureSource) {
            $entry['source'] = $this->source();
        }

        $this->queries[] = $entry;
    }

    // First file:line outside vendor/ and outside this package: the app code that ran the query.
    private function source(): ?string
    {
        $skip = [str_replace('\\', '/', __DIR__).'/', str_replace('\\', '/', public_path('index.php')), str_replace('\\', '/', base_path('artisan'))];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = str_replace('\\', '/', $frame['file'] ?? '');

            if ($file === '' || str_contains($file, '/vendor/') || str_starts_with($file, $skip[0]) || in_array($file, $skip, true)) {
                continue;
            }

            $base = str_replace('\\', '/', base_path()).'/';

            return (str_starts_with($file, $base) ? substr($file, strlen($base)) : $file).':'.($frame['line'] ?? 0);
        }

        return null; // framework-only, e.g. the session lookup
    }

    /** @return array{queries: array, dropped: int, n_plus_one: array} */
    public function stop(): array
    {
        $this->active = false;

        $captured = [
            'queries' => $this->queries,
            'dropped' => $this->dropped,
            'n_plus_one' => $this->detectNPlusOne($this->queries),
        ];

        $this->queries = [];

        return $captured;
    }

    public function queue(array $row): void
    {
        $this->pending = $row;
    }

    // One INSERT per recorded request. Taking clears it, so a reused app never saves a row twice.
    public function save(): void
    {
        if ($this->pending === null) {
            return;
        }

        [$row, $this->pending] = [$this->pending, null];

        try {
            Lane::create($row);
        } catch (Throwable $e) {
            $this->reportOnce($e);
        }
    }

    // Golden rule 1: never break the host. Swallow, and warn at most once an hour.
    public function reportOnce(Throwable $e): void
    {
        if ($this->failureLogged) {
            return;
        }

        $this->failureLogged = true;

        try {
            // The property only lasts one process, and under FPM that's one request, so the cache carries it across.
            if (! Cache::add('memorylane:failure-logged', true, now()->addHour())) {
                return;
            }
        } catch (Throwable) {
            // Cache is broken too; fall through and log anyway.
        }

        try {
            $hint = str_contains($e->getMessage(), 'memorylane_requests') ? ' (has the migration run?)' : '';
            Log::warning('MemoryLane failed and is skipping recording'.$hint.': '.$e->getMessage());
        } catch (Throwable) {
            // Logging itself is broken; nothing left to do.
        }
    }

    // Eloquent already hands us "where id = ?" shapes, so the SQL string is the group key.
    private function detectNPlusOne(array $queries): array
    {
        $groups = [];

        foreach ($queries as $query) {
            $groups[$query['sql']] ??= ['sql' => $query['sql'], 'count' => 0, 'time_ms' => 0.0, 'source' => $query['source'] ?? null];
            $groups[$query['sql']]['count']++;
            $groups[$query['sql']]['time_ms'] += $query['time_ms'];
        }

        $threshold = (int) config('memorylane.n_plus_one_threshold');

        $flagged = array_values(array_filter($groups, fn ($group) => $group['count'] >= $threshold));

        usort($flagged, fn ($a, $b) => $b['count'] <=> $a['count']);

        return array_map(fn ($group) => [...$group, 'time_ms' => round($group['time_ms'], 2)], $flagged);
    }

    private function bindingForStorage(mixed $binding): mixed
    {
        return match (true) {
            $binding instanceof DateTimeInterface => $binding->format('Y-m-d H:i:s'),
            is_string($binding) => Str::limit($binding, 200),
            is_scalar($binding), is_null($binding) => $binding,
            default => get_debug_type($binding),
        };
    }
}
