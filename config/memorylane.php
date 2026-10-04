<?php

return [

    // Kill switch. false = the package registers nothing and records nothing.
    'enabled' => env('MEMORYLANE_ENABLED', true),

    // Share of requests recorded, 0.0–1.0. 0.1 = one in ten.
    'sample_rate' => env('MEMORYLANE_SAMPLE_RATE', 0.1),

    // Requests at least this slow (ms) are always recorded, whatever the sample rate.
    'always_record_slow' => 1000,

    // The same query shape this many times in one request = flagged as N+1.
    'n_plus_one_threshold' => 10,

    // A single query at least this slow (ms) is highlighted.
    'slow_query_ms' => 100,

    // Lighthouse-style grade from request duration (ms upper bounds). Slower = F. An N+1 drops one letter.
    'grades' => ['A' => 100, 'B' => 300, 'C' => 600, 'D' => 1000],

    // Record the app file:line that ran each query (first frame outside vendor/).
    'capture_source' => true,

    // Stop buffering queries past this many per request (protects memory on runaway loops).
    'max_queries' => 500,

    // Lanes older than this are deleted by the hourly prune.
    'keep_hours' => 72,

    // Database connection for the memorylane_requests table. null = the app's default.
    'connection' => env('MEMORYLANE_DB_CONNECTION'),

    // Store query binding values. Off by default: only shapes like "where id = ?" are kept.
    'capture_bindings' => false,

    // Paths that are never recorded ($request->is() patterns).
    'ignore' => ['memorylane*', 'horizon*', 'up'],

    // Dashboard URL and middleware. "web" is needed for the login session.
    'path' => 'memorylane',
    'middleware' => ['web'],

    // Dashboard login for staging/production. Local is always open.
    // Both unset = the dashboard is locked (403) outside local, unless the viewMemoryLane gate allows the user.
    'username' => env('MEMORYLANE_USERNAME'),
    'password' => env('MEMORYLANE_PASSWORD'),

];
