@extends('memorylane::layout')

@section('title', $lane->method.' '.$lane->path)

@php
    use Thirdestonks\MemoryLane\Models\Lane;

    $nPlusOne = $lane->payload['n_plus_one'] ?? [];
    $queries = $lane->payload['queries'] ?? [];
    $dropped = $lane->payload['dropped_queries'] ?? 0;
    $repeated = array_column($nPlusOne, 'count', 'sql');
    $statusTone = $lane->status >= 500 ? 'slow' : ($lane->status >= 400 ? 'warn' : 'ok');
    $grade = Lane::gradeFor($lane->duration_ms, $lane->n_plus_one_count);
    $slowQueryMs = (float) config('memorylane.slow_query_ms');
    $slowQueries = count(array_filter($queries, fn ($q) => $q['time_ms'] >= $slowQueryMs));

    // Decorative barcode: 32 bars from a hash of the id, thin or thick per bit.
    $bars = str_split(sprintf('%032b', crc32('memorylane-'.$lane->id)));

    // "SELECT · tesda_sectors": what the query does and to which table, so long SQL doesn't bury the row.
    $summary = function (string $sql): array {
        preg_match('/^\W*(\w+)/', $sql, $verb);
        preg_match('/\b(?:from|into|update|join)\s+[`"\[]?([\w.]+)/i', $sql, $table);

        return [strtoupper($verb[1] ?? 'SQL'), $table[1] ?? null];
    };
@endphp

@section('content')
    <p style="margin:0 0 16px"><a href="{{ route('memorylane.index') }}" class="dim cap" style="text-decoration:none;font-size:11px">← All requests</a></p>

    <div class="panel" style="padding:20px;display:flex;flex-wrap:wrap;gap:20px 32px;align-items:flex-start;justify-content:space-between">
        <div style="flex:1 1 360px;min-width:0">
            <h1 style="font-size:20px;margin:0;word-break:break-all"><span class="m" style="font-size:14px">{{ $lane->method }}</span>{{ $lane->path }}</h1>
            <hr style="border:0;border-top:2px solid var(--fg);margin:10px 0 12px">
            <dl class="spec cap" style="grid-template-columns:110px 1fr">
                <dt>route</dt><dd style="text-transform:none">{{ $lane->route ?? '—' }}</dd>
                <dt>recorded</dt><dd>{{ $lane->created_at }}</dd>
                <dt>peak memory</dt><dd>{{ number_format($lane->peak_memory_mb, 1) }} MB</dd>
                <dt>db time</dt><dd>{{ number_format($lane->query_time_ms, 1) }} ms</dd>
            </dl>
        </div>
        <div style="text-align:right">
            <span style="display:inline-flex;gap:8px;align-items:center">
                <img class="sprite" src="{{ \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::spriteForGrade($grade) }}" alt="" width="56" height="56">
                <span class="stamp grade tilt {{ Lane::gradeTone($grade) }}" title="Grade">{{ $grade }}</span>
                <span class="stamp {{ $statusTone }}" style="font-size:20px;line-height:32px;padding:0 12px">{{ $lane->status }}</span>
            </span>
            <p class="cap dim" style="margin:14px 0 2px;font-size:10px">Serial no.</p>
            <p style="margin:0 0 6px;font-size:15px">ML-{{ str_pad($lane->id, 6, '0', STR_PAD_LEFT) }}</p>
            <svg width="132" height="26" aria-hidden="true" style="display:block;margin-left:auto">
                @php $x = 0; @endphp
                @foreach ($bars as $bit)
                    <rect x="{{ $x }}" y="0" width="{{ $bit ? 2.5 : 1 }}" height="26" fill="var(--fg)"/>
                    @php $x += $bit ? 4.5 : 3; @endphp
                @endforeach
            </svg>
        </div>
    </div>

    {{-- Input → work → output, like the flow line on a spec sheet. --}}
    <div class="panel cap" style="display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;justify-content:space-between;padding:14px 20px;font-size:11px">
        <span><span class="dim">Request</span><br>{{ $lane->method }}</span>
        <span class="dim">⟶</span>
        <span style="border-left:2px solid var(--purple);border-right:2px solid var(--purple);padding:2px 12px;text-align:center">{{ $lane->query_count }} queries<br><span class="{{ $lane->n_plus_one_count ? 'slow' : 'dim' }}">{{ $lane->n_plus_one_count }} N+1 patterns</span> · <span class="{{ $slowQueries ? 'slow' : 'dim' }}">{{ $slowQueries }} slow (≥ {{ $slowQueryMs + 0 }} ms)</span></span>
        <span class="dim">⟶</span>
        <span style="text-align:right"><span class="dim">Response</span><br><span class="{{ Lane::toneFor($lane->duration_ms) }}">{{ number_format($lane->duration_ms, 1) }} ms</span></span>
    </div>

    {{-- One Alpine scope for the N+1 box, the manifest and the query modal. --}}
    <div x-data="{ mode: 'all', sel: null }" @keydown.escape.window="sel = null">
    @if ($nPlusOne)
        <div class="panel" style="border-color:var(--slow)">
            <h2 style="color:var(--slow);border-color:var(--slow)">
                <span class="cap">N+1: the same query ran over and over</span>
                <span class="stamp tilt">Handle with care</span>
            </h2>
            <div class="scroll">
                <table class="sql">
                    <thead><tr><th class="num">Times</th><th class="num">Total</th><th>Query</th></tr></thead>
                    <tbody>
                    @foreach ($nPlusOne as $group)
                        @php [$verb, $table] = $summary($group['sql']); @endphp
                        <tr class="q" data-sql="{{ $group['sql'] }}" data-label="N+1 ×{{ $group['count'] }} · {{ number_format($group['time_ms'], 1) }} ms total{{ empty($group['source']) ? '' : ' · '.$group['source'] }}"
                            @click="sel = { sql: $el.dataset.sql, label: $el.dataset.label }">
                            <td class="num slow"><b>×{{ $group['count'] }}</b></td>
                            <td class="num">{{ number_format($group['time_ms'], 1) }} ms</td>
                            <td class="qsum"><span class="verb">{{ $verb }}</span>@if ($table)<span class="tbl">{{ $table }}</span>@endif
                                @if (! empty($group['source']))<span class="src">{{ $group['source'] }}</span>@endif
                                <span class="preview">{{ $group['sql'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="dim" style="margin:0;padding:10px 16px;border-top:1px solid var(--line)">Usually fixed by eager loading the relation, e.g. <code>-&gt;with('relation')</code>.</p>
        </div>
    @endif

    {{-- Alpine: filter / re-sort the query list in place. Rows carry their own data-*, so no JSON round-trip. --}}
    <div class="panel">
        <h2>
            <span class="cap">Query manifest</span>
            @if ($queries)
                <span style="display:flex;gap:6px">
                    <button type="button" class="chip" :class="mode === 'all' && 'on'" @click="mode = 'all'">In order</button>
                    <button type="button" class="chip" :class="mode === 'slow' && 'on'" @click="mode = 'slow'">Slowest first</button>
                    @if ($nPlusOne)<button type="button" class="chip" :class="mode === 'n1' && 'on'" @click="mode = 'n1'">N+1 only</button>@endif
                </span>
            @endif
        </h2>
        @if ($dropped)
            <div class="note">{{ $dropped }} more queries ran but weren't kept (limit {{ config('memorylane.max_queries') }} per request).</div>
        @endif
        @if (! $queries)
            <div class="empty">No queries.</div>
        @else
            <div class="scroll">
                <table class="sql">
                    <thead><tr><th class="num">#</th><th class="num">Time</th><th>Query</th></tr></thead>
                    <tbody x-effect="[...$el.children].sort((a, b) => mode === 'slow' ? b.dataset.ms - a.dataset.ms : a.dataset.i - b.dataset.i).forEach(row => $el.appendChild(row))">
                    @foreach ($queries as $i => $query)
                        @php [$verb, $table] = $summary($query['sql']); @endphp
                        <tr class="q" data-i="{{ $i }}" data-ms="{{ $query['time_ms'] }}" data-n1="{{ isset($repeated[$query['sql']]) ? 1 : 0 }}"
                            data-sql="{{ $query['sql'] }}" data-bindings="{{ isset($query['bindings']) ? json_encode($query['bindings']) : '' }}"
                            data-label="Query #{{ $i + 1 }} · {{ number_format($query['time_ms'], 2) }} ms{{ empty($query['source']) ? '' : ' · '.$query['source'] }}"
                            x-show="mode !== 'n1' || $el.dataset.n1 === '1'"
                            @click="sel = { sql: $el.dataset.sql, bindings: $el.dataset.bindings, label: $el.dataset.label }">
                            <td class="num dim">{{ $i + 1 }}</td>
                            <td class="num {{ Lane::queryToneFor($query['time_ms']) }}">{{ number_format($query['time_ms'], 2) }} ms</td>
                            <td class="qsum">
                                <span class="verb">{{ $verb }}</span>@if ($table)<span class="tbl">{{ $table }}</span>@endif
                                @if ($query['time_ms'] >= $slowQueryMs)<span class="stamp slow">Slow</span>@endif
                                @isset($repeated[$query['sql']])<span class="stamp slow">N+1 ×{{ $repeated[$query['sql']] }}</span>@endisset
                                @if (! empty($query['source']))<span class="src">{{ $query['source'] }}</span>@endif
                                <span class="preview">{{ $query['sql'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Query modal: full SQL, one clause per line, with copy. --}}
    <div class="modal-bg" x-show="sel" x-cloak x-transition.opacity @click.self="sel = null">
        <div class="modal" role="dialog" aria-modal="true" x-show="sel" x-transition>
            <h2>
                <span class="cap" x-text="sel?.label"></span>
                <span style="display:flex;gap:6px">
                    <button type="button" class="chip" x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText(sel.sql); copied = true; setTimeout(() => copied = false, 1200)"
                            x-text="copied ? 'Copied' : 'Copy'"></button>
                    <button type="button" class="chip" @click="sel = null" aria-label="Close">✕</button>
                </span>
            </h2>
            <pre class="sql-full" x-text="sel?.sql.replace(/\s+(from|where|inner join|left join|right join|join|on|and|or|order by|group by|having|limit|offset|set|values)\s+/gi, '\n$1 ')"></pre>
            <template x-if="sel?.bindings">
                <pre class="sql-full dim" style="border-top:1px solid var(--line)" x-text="'bindings: ' + sel.bindings"></pre>
            </template>
        </div>
    </div>
    </div>
@endsection
