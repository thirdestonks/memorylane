@extends('memorylane::layout')

@section('title', 'Dashboard')

@php
    use Thirdestonks\MemoryLane\Models\Lane;

    $ms = fn ($v) => $v === null ? '—' : number_format((float) $v, $v < 10 ? 1 : 0).' ms';
    $statusTone = fn ($s) => $s >= 500 ? 'slow' : ($s >= 400 ? 'warn' : 'ok');
    $maxOnPage = max(1, (float) $lanes->max('duration_ms'));
    $maxEndpoint = max(1, (float) $endpoints->max('avg_ms'));
@endphp

@section('content')
    @if (session('memorylane.status'))
        <div class="panel" style="padding:10px 16px;border-color:var(--purple);color:var(--purple)">{{ session('memorylane.status') }}</div>
    @endif

    @php $overall = $stats->total ? Lane::gradeFor((float) $stats->avg_ms) : null; @endphp
    <div class="tiles">
        <div class="tile"><small class="cap">Grade, 24h average</small><b style="gap:10px">@if ($overall)<img class="sprite" src="{{ \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::spriteForGrade($overall) }}" alt="" width="44" height="44"><span class="stamp grade {{ Lane::gradeTone($overall) }}">{{ $overall }}</span>@else — @endif</b></div>
        <div class="tile"><small class="cap">Recorded, last 24h</small><b>{{ number_format($stats->total ?? 0) }}</b></div>
        <div class="tile"><small class="cap">Average</small><b class="{{ Lane::toneFor((float) $stats->avg_ms) }}">{{ $ms($stats->avg_ms) }}</b></div>
        <div class="tile"><small class="cap">Slowest</small><b class="{{ Lane::toneFor((float) $stats->max_ms) }}">{{ $ms($stats->max_ms) }}</b></div>
        <div class="tile"><small class="cap">Requests with N+1</small><b class="{{ ($stats->n1 ?? 0) > 0 ? 'slow' : '' }}">{{ number_format($stats->n1 ?? 0) }}</b></div>
    </div>

    @if ($endpoints->isNotEmpty())
        <div class="panel">
            <h2><span class="cap">Top endpoints by impact, last 24h</span><span class="dim" style="font-weight:400">impact = hits × average</span></h2>
            <div class="scroll">
                <table>
                    <thead><tr><th>Grade</th><th>Endpoint</th><th class="num">Hits</th><th>Average</th><th class="num">Worst</th><th class="num">Total time</th></tr></thead>
                    <tbody>
                    @foreach ($endpoints as $endpoint)
                        @php $grade = Lane::gradeFor((float) $endpoint->avg_ms, (int) $endpoint->n1); @endphp
                        <tr>
                            <td><span class="stamp {{ Lane::gradeTone($grade) }}">{{ $grade }}</span></td>
                            <td class="path"><span class="m">{{ $endpoint->method }}</span><a href="{{ route('memorylane.index', ['route' => $endpoint->route, 'method' => $endpoint->method]) }}#lanes">{{ $endpoint->route }}</a></td>
                            <td class="num">{{ number_format($endpoint->hits) }}</td>
                            <td class="{{ Lane::toneFor((float) $endpoint->avg_ms) }}"><span class="bar" style="width:{{ round($endpoint->avg_ms / $maxEndpoint * 140) }}px"></span>{{ $ms($endpoint->avg_ms) }}</td>
                            <td class="num {{ Lane::toneFor((float) $endpoint->max_ms) }}">{{ $ms($endpoint->max_ms) }}</td>
                            <td class="num">{{ $ms($endpoint->total_ms) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @php $keep = array_filter(['route' => $route, 'method' => $method]); @endphp
    <div x-data="{ q: '' }" id="lanes">
        <div class="bar-row">
            <nav class="tabs">
                <a href="{{ route('memorylane.index', ['view' => 'recent', ...$keep]) }}" class="{{ $view === 'recent' ? 'on' : '' }}">Recent</a>
                <a href="{{ route('memorylane.index', ['view' => 'slow', ...$keep]) }}" class="{{ $view === 'slow' ? 'on' : '' }}">Slow</a>
                <a href="{{ route('memorylane.index', ['view' => 'n1', ...$keep]) }}" class="{{ $view === 'n1' ? 'on' : '' }}">N+1</a>
                @if ($route)
                    <a href="{{ route('memorylane.index', ['view' => $view]) }}" class="on" title="Clear filter" style="text-transform:none;letter-spacing:0">{{ $method }} {{ $route }} ✕</a>
                @endif
            </nav>
            <input type="search" x-model="q" placeholder="filter this page by path…" aria-label="Filter by path">
        </div>

        <div class="panel">
            @if ($lanes->isEmpty())
                <div class="empty"><img class="sprite" src="{{ \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::sprite('hi') }}" alt="" width="96" height="96" style="margin:0 auto 10px">No requests recorded yet. Browse your app; about {{ round(config('memorylane.sample_rate') * 100) }}% of requests are saved here.</div>
            @else
                <div class="scroll">
                    <table>
                        <thead><tr><th>Grade</th><th>When</th><th>Request</th><th>Status</th><th>Duration</th><th class="num">Memory</th><th class="num">Queries</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($lanes as $lane)
                            @php $grade = Lane::gradeFor($lane->duration_ms, $lane->n_plus_one_count); @endphp
                            <tr data-path="{{ strtolower($lane->method.' '.$lane->path) }}" x-show="! q || $el.dataset.path.includes(q.toLowerCase())">
                                <td><span class="stamp {{ Lane::gradeTone($grade) }}">{{ $grade }}</span></td>
                                <td class="dim" title="{{ $lane->created_at }}">{{ $lane->created_at?->diffForHumans(short: true) }}</td>
                                <td class="path"><span class="m">{{ $lane->method }}</span><a href="{{ route('memorylane.show', $lane) }}">{{ $lane->path }}</a></td>
                                <td><span class="stamp {{ $statusTone($lane->status) }}">{{ $lane->status }}</span></td>
                                <td class="{{ Lane::toneFor($lane->duration_ms) }}"><span class="bar" style="width:{{ round($lane->duration_ms / $maxOnPage * 140) }}px"></span>{{ $ms($lane->duration_ms) }}</td>
                                <td class="num">{{ number_format($lane->peak_memory_mb, 1) }} MB</td>
                                <td class="num">{{ $lane->query_count }}</td>
                                <td>@if ($lane->n_plus_one_count > 0)<span class="stamp tilt slow">N+1</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($lanes->hasPages())
                    <div class="pager">
                        @if ($lanes->previousPageUrl())<a href="{{ $lanes->previousPageUrl() }}">← Newer</a>@endif
                        @if ($lanes->nextPageUrl())<a href="{{ $lanes->nextPageUrl() }}">Older →</a>@endif
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Housekeeping: two-step confirm for both actions; "clear" must also be typed (checked again on the server). --}}
    <div class="panel" x-data="{ act: null, step: 1, typed: '' }" @keydown.escape.window="act = null">
        <h2>
            <span class="cap">Housekeeping · {{ number_format($stored) }} lanes stored</span>
            <span style="display:flex;gap:6px">
                <button type="button" class="chip" @click="act = 'prune'; step = 1">Prune &gt; {{ config('memorylane.keep_hours') }}h ({{ number_format($prunable) }})</button>
                <button type="button" class="chip danger" @click="act = 'clear'; step = 1; typed = ''">Clear all</button>
            </span>
        </h2>

        <div class="modal-bg" x-show="act" x-cloak x-transition.opacity @click.self="act = null">
            <div class="modal" role="dialog" aria-modal="true" style="max-width:460px">
                <template x-if="act === 'prune'">
                    <form method="post" action="{{ route('memorylane.prune') }}" style="padding:20px">
                        @csrf
                        <p class="cap slow" style="margin:0 0 10px;font-weight:700" x-text="step === 1 ? 'Prune old lanes?' : 'Last check'"></p>
                        <p style="margin:0 0 18px" x-show="step === 1">This deletes the {{ number_format($prunable) }} lane(s) older than {{ config('memorylane.keep_hours') }}h. Newer lanes stay.</p>
                        <p style="margin:0 0 18px" x-show="step === 2">Deleted lanes can't be brought back. Prune {{ number_format($prunable) }} lane(s) now?</p>
                        <div style="display:flex;gap:8px;justify-content:flex-end">
                            <button type="button" class="chip" @click="act = null">Cancel</button>
                            <button type="button" class="chip danger" x-show="step === 1" @click="step = 2">Continue</button>
                            <button type="submit" class="chip danger on" x-show="step === 2">Yes, prune</button>
                        </div>
                    </form>
                </template>
                <template x-if="act === 'clear'">
                    <form method="post" action="{{ route('memorylane.clear') }}" style="padding:20px">
                        @csrf
                        <p class="cap slow" style="margin:0 0 10px;font-weight:700" x-text="step === 1 ? 'Clear ALL lanes?' : 'Type clear to confirm'"></p>
                        <p style="margin:0 0 18px" x-show="step === 1">This deletes all {{ number_format($stored) }} lane(s). The table stays, and recording carries on.</p>
                        <div x-show="step === 2" style="margin:0 0 18px">
                            <p style="margin:0 0 10px">Deleted lanes can't be brought back.</p>
                            <input name="confirm" x-model="typed" placeholder="clear" autocomplete="off" style="width:100%">
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end">
                            <button type="button" class="chip" @click="act = null">Cancel</button>
                            <button type="button" class="chip danger" x-show="step === 1" @click="step = 2">Continue</button>
                            <button type="submit" class="chip danger on" x-show="step === 2" :disabled="typed !== 'clear'">Delete everything</button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>
@endsection
