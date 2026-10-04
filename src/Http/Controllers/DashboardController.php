<?php

namespace Thirdestonks\MemoryLane\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Thirdestonks\MemoryLane\Models\Lane;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $view = in_array($request->query('view'), ['slow', 'n1'], true) ? $request->query('view') : 'recent';

        // Set by clicking an endpoint: narrow the list to that route.
        $route = $request->string('route')->toString() ?: null;
        $method = $route ? (strtoupper($request->string('method')->toString()) ?: null) : null;

        // Everything but the payload: list rows stay small.
        $lanes = Lane::query()
            ->select(['id', 'method', 'path', 'route', 'status', 'duration_ms', 'peak_memory_mb', 'query_count', 'n_plus_one_count', 'created_at'])
            ->when($route, fn ($q) => $q->where('route', $route))
            ->when($method, fn ($q) => $q->where('method', $method))
            ->when($view === 'slow', fn ($q) => $q->where('duration_ms', '>=', config('memorylane.always_record_slow'))->orderByDesc('duration_ms'))
            ->when($view === 'n1', fn ($q) => $q->where('n_plus_one_count', '>', 0)->orderByDesc('id'))
            ->when($view === 'recent', fn ($q) => $q->orderByDesc('id'))
            ->simplePaginate(50)
            ->withQueryString();

        $since = now()->subDay();

        $stats = Lane::query()->toBase()
            ->where('created_at', '>=', $since)
            ->selectRaw('count(*) as total, avg(duration_ms) as avg_ms, max(duration_ms) as max_ms, sum(case when n_plus_one_count > 0 then 1 else 0 end) as n1')
            ->first();

        $endpoints = Lane::query()->toBase()
            ->where('created_at', '>=', $since)
            ->whereNotNull('route')
            // Impact = total time spent (hits × average). A busy 200 ms route outranks a rare 800 ms one.
            ->selectRaw('method, route, count(*) as hits, avg(duration_ms) as avg_ms, max(duration_ms) as max_ms, sum(duration_ms) as total_ms, max(n_plus_one_count) as n1')
            ->groupBy('method', 'route')
            ->orderByDesc('total_ms')
            ->limit(5)
            ->get();

        $stored = Lane::count();
        $prunable = Lane::olderThan((int) config('memorylane.keep_hours'))->count();

        return view('memorylane::index', compact('view', 'lanes', 'stats', 'endpoints', 'route', 'method', 'stored', 'prunable'));
    }

    public function prune()
    {
        $hours = (int) config('memorylane.keep_hours');
        $deleted = Lane::olderThan($hours)->delete();

        return redirect()->route('memorylane.index')->with('memorylane.status', "Pruned {$deleted} lane(s) older than {$hours}h.");
    }

    // Deletes every row but keeps the table, so the dashboard keeps working. Dropping the table is CLI-only (memorylane:uninstall).
    public function clear(Request $request)
    {
        // Checked here too, not just in the modal: a hand-built POST without the typed word does nothing.
        if ($request->input('confirm') !== 'clear') {
            return redirect()->route('memorylane.index')->with('memorylane.status', 'Nothing deleted: type "clear" to confirm.');
        }

        $deleted = Lane::query()->delete();

        return redirect()->route('memorylane.index')->with('memorylane.status', "Cleared all {$deleted} lane(s).");
    }

    // Plain int, not route-model binding: binding runs before our auth middleware and would 404-leak which ids exist.
    public function show(int $lane)
    {
        return view('memorylane::show', ['lane' => Lane::findOrFail($lane)]);
    }

    public static function alpine(): string
    {
        return file_get_contents(__DIR__.'/../../../resources/dist/alpine.min.js');
    }

    // Lane the mascot, inlined as a data URI (~3.6 KB each) so the dashboard still makes no extra requests.
    public static function sprite(string $name): string
    {
        static $cache = [];

        return $cache[$name] ??= 'data:image/png;base64,'.base64_encode(file_get_contents(__DIR__."/../../../resources/sprites/{$name}.png"));
    }

    public static function spriteForGrade(string $grade): string
    {
        return self::sprite(match ($grade) {
            'A', 'B' => 'okay',
            'C', 'D' => 'disappointed',
            default => 'x',
        });
    }
}
