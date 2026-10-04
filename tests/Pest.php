<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Thirdestonks\MemoryLane\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

function allowDashboard(): void
{
    Gate::define('viewMemoryLane', fn ($user = null) => true);
}
