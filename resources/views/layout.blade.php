<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark">
<title>@yield('title') · MemoryLane</title>
<style>
/* Dracula, always. Spec-sheet look: mono caps, thin rules, stamps. System fonts only, so no outside requests. */
:root{--bg:#1e1f29;--panel:#282a36;--line:#44475a;--fg:#f8f8f2;--dim:#6272a4;--purple:#bd93f9;--pink:#ff79c6;--cyan:#8be9fd;--ok:#50fa7b;--warn:#ffb86c;--slow:#ff5555;--mono:ui-monospace,"JetBrains Mono","Cascadia Code",Consolas,"SF Mono",monospace}
*{box-sizing:border-box}
html{background:var(--bg)}
body{margin:0;background:var(--bg);color:var(--fg);font:13px/1.55 var(--mono)}
a{color:inherit}
::selection{background:var(--purple);color:var(--bg)}
.cap{text-transform:uppercase;letter-spacing:.08em}
.dim{color:var(--dim)}
.ok{color:var(--ok)}.warn{color:var(--warn)}.slow{color:var(--slow)}

/* masthead */
.masthead{max-width:1200px;margin:0 auto;padding:24px 16px 0;display:flex;flex-wrap:wrap;gap:16px 28px;align-items:stretch}
.mark{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;width:96px;padding:10px 8px;border:2px solid var(--purple);border-radius:10px;text-decoration:none;color:var(--purple)}
.mark{position:relative}
.sprite{image-rendering:pixelated;display:block}
/* Lane waves hi on every load: a few wiggles from the bottom, then rests. */
.lane-hi{width:64px;height:64px;image-rendering:pixelated;transform-origin:50% 90%;animation:lane-hi .45s ease-in-out .2s 3}
@keyframes lane-hi{0%,100%{transform:rotate(0)}25%{transform:rotate(-9deg)}75%{transform:rotate(9deg)}}
.hi-bubble{position:absolute;top:-10px;right:-14px;padding:1px 6px;border:1.5px solid var(--pink);border-radius:6px;background:var(--bg);color:var(--pink);font-weight:700;font-size:11px;opacity:0;animation:hi-bubble 2.2s ease .1s 1}
@keyframes hi-bubble{0%{opacity:0;transform:translateY(4px)}15%,75%{opacity:1;transform:none}100%{opacity:0}}
.mark>span:last-child{font-weight:700;font-size:11px;line-height:1.1;text-align:center;letter-spacing:.06em;padding-top:6px;border-top:2px solid var(--purple)}
.title{flex:1 1 320px;display:flex;flex-direction:column;justify-content:center;min-width:0}
.title h1{margin:0;font-size:30px;line-height:1;font-weight:700;letter-spacing:.06em}
.title h1 sup{font-size:11px;color:var(--dim);letter-spacing:0;margin-left:6px}
.title hr{border:0;border-top:2px solid var(--fg);margin:10px 0 8px;max-width:520px}
.title p{margin:0;color:var(--dim);font-size:12px}
.spec{display:grid;grid-template-columns:auto auto;gap:2px 16px;align-content:center;margin:0;font-size:12px}
.spec dt{color:var(--dim)}
.spec dd{margin:0}
.spec form{grid-column:1/-1;margin:8px 0 0}

main{max-width:1200px;margin:0 auto;padding:20px 16px 64px}

/* boxes */
.panel{background:var(--panel);border:1px solid var(--line);border-radius:4px;margin:0 0 16px;overflow:hidden}
.panel>h2{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin:0;padding:10px 16px;border-bottom:1px solid var(--line);font-size:11px;font-weight:700;color:var(--dim)}
.tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin:0 0 16px}
.tile{display:grid;grid-template-columns:auto 1fr;border:1.5px solid var(--line);border-radius:4px;background:var(--panel)}
.tile small{display:flex;align-items:center;padding:10px 12px;border-right:1.5px solid var(--line);color:var(--dim);font-size:10px;line-height:1.3;max-width:96px}
.tile b{display:flex;align-items:center;padding:10px 14px;font-size:22px;font-variant-numeric:tabular-nums;white-space:nowrap}

/* stamps */
.stamp{display:inline-block;padding:0 6px;border:1.5px solid currentColor;border-radius:3px;font-weight:700;font-size:11px;letter-spacing:.06em;line-height:18px;text-transform:uppercase}
.stamp.tilt{transform:rotate(-4deg)}
.m{color:var(--purple);font-weight:700;font-size:11px;margin-right:6px}

/* tables */
.scroll{overflow-x:auto}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:8px 14px;border-bottom:1px solid var(--line);white-space:nowrap;vertical-align:middle}
th{font-size:10px;font-weight:700;color:var(--dim);text-transform:uppercase;letter-spacing:.08em}
tr:last-child td{border-bottom:0}
tbody tr:hover{background:#2f3241}
.num{text-align:right;font-variant-numeric:tabular-nums}
.path{max-width:460px;overflow:hidden;text-overflow:ellipsis}
.path a{text-decoration:none}
.path a:hover{color:var(--pink)}
.bar{display:inline-block;height:4px;background:currentColor;vertical-align:middle;margin-right:10px;min-width:2px}
pre{margin:0;font:12px/1.55 var(--mono);white-space:pre-wrap;word-break:break-word}
.sql td{white-space:normal}
.sql td.num{white-space:nowrap}

/* query rows: verb + table first, SQL as a one-line preview; full text in the modal */
tr.q{cursor:pointer}
tr.q:hover .tbl{color:var(--pink)}
.qsum{max-width:0;width:100%}
.qsum>*{vertical-align:middle}
.verb{display:inline-block;min-width:58px;color:var(--purple);font-weight:700;font-size:11px;letter-spacing:.06em}
.tbl{margin-right:10px;font-weight:700}
.qsum .stamp{margin-right:10px}
.src{color:var(--cyan);font-size:11px}
.stamp.grade{font-size:18px;line-height:28px;min-width:32px;text-align:center}
.tile .stamp.grade{font-size:20px}
.preview{display:block;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--dim);font-size:12px}
.modal-bg{position:fixed;inset:0;z-index:50;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,16,22,.75)}
.modal{width:100%;max-width:900px;max-height:85vh;overflow:auto;background:var(--panel);border:1.5px solid var(--purple);border-radius:4px}
.modal>h2{position:sticky;top:0;display:flex;gap:8px;align-items:center;justify-content:space-between;margin:0;padding:10px 16px;background:var(--panel);border-bottom:1px solid var(--line);font-size:11px;color:var(--dim)}
.sql-full{padding:16px;font-size:13px;line-height:1.7;color:var(--fg)}
code{color:var(--cyan)}

/* controls */
.bar-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;margin:0 0 12px}
.tabs{display:flex;gap:4px}
.tabs a{padding:5px 10px;border:1.5px solid transparent;border-radius:3px;text-decoration:none;color:var(--dim);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
.tabs a:hover{color:var(--fg)}
.tabs a.on{color:var(--purple);border-color:var(--purple)}
input{font:inherit;color:var(--fg);background:var(--bg);border:1.5px solid var(--line);border-radius:3px;padding:6px 10px}
input:focus{outline:0;border-color:var(--purple)}
input[type=search]{min-width:240px}
button.chip{font:inherit;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--dim);background:none;border:1.5px solid var(--line);border-radius:3px;padding:2px 8px;cursor:pointer}
button.chip:hover{color:var(--fg)}
button.chip.on{color:var(--purple);border-color:var(--purple)}
button.chip.danger{color:var(--slow);border-color:var(--slow)}
button.chip.danger.on{color:var(--bg);background:var(--slow)}
button.chip:disabled{opacity:.35;cursor:not-allowed}
button.primary{width:100%;font:inherit;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--bg);background:var(--purple);border:0;border-radius:3px;padding:10px;cursor:pointer}
button.primary:hover{background:var(--pink)}
.field{display:block;margin:0 0 14px;font-size:10px;font-weight:700;color:var(--dim);text-transform:uppercase;letter-spacing:.08em}
.field input{display:block;width:100%;margin-top:6px;font-size:13px;text-transform:none;letter-spacing:0}
.pager{display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid var(--line)}
.pager a{padding:4px 10px;border:1.5px solid var(--line);border-radius:3px;text-decoration:none;font-size:11px;text-transform:uppercase;letter-spacing:.08em}
.pager a:hover{border-color:var(--purple);color:var(--purple)}
.empty{padding:36px 16px;text-align:center;color:var(--dim)}
.note{padding:10px 16px;border-bottom:1px solid var(--line);color:var(--warn)}
[x-cloak]{display:none!important}
</style>
</head>
<body>
<header class="masthead">
    <a class="mark" href="{{ route('memorylane.index') }}" aria-label="MemoryLane home">
        <span class="hi-bubble" aria-hidden="true">hi!</span>
        <img class="lane-hi" src="{{ \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::sprite('hi') }}" alt="" width="64" height="64">
        <span>MEMORY<br>LANE</span>
    </a>
    <div class="title">
        <h1>MEMORYLANE<sup>v0.1</sup></h1>
        <hr>
        <p class="cap">Backend performance monitor</p>
        <p class="cap">What's slow, right now</p>
    </div>
    @unless (request()->routeIs('memorylane.login'))
        <dl class="spec cap">
            <dt>sampling</dt><dd>{{ round(config('memorylane.sample_rate') * 100) }}%</dd>
            <dt>always ≥</dt><dd>{{ number_format(config('memorylane.always_record_slow')) }} ms</dd>
            <dt>retention</dt><dd>{{ config('memorylane.keep_hours') }}h</dd>
            <dt>env</dt><dd>{{ app()->environment() }}</dd>
            @if (\Thirdestonks\MemoryLane\Http\Middleware\Authenticate::loggedIn(request()))
                <form method="post" action="{{ route('memorylane.logout') }}">@csrf<button type="submit" class="chip">Log out</button></form>
            @endif
        </dl>
    @endunless
</header>
<main>
    @yield('content')
</main>
{{-- Bundled Alpine, inlined: no CDN call from prod and no build step for the host. Last in body so the DOM exists when it starts. --}}
<script>{!! \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::alpine() !!}</script>
</body>
</html>
