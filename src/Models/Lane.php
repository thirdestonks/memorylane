<?php

namespace Thirdestonks\MemoryLane\Models;

use Illuminate\Database\Eloquent\Model;

class Lane extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'memorylane_requests';

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'duration_ms' => 'float',
        'peak_memory_mb' => 'float',
        'query_count' => 'integer',
        'query_time_ms' => 'float',
        'n_plus_one_count' => 'integer',
        'payload' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return config('memorylane.connection') ?? parent::getConnectionName();
    }

    public static function olderThan(int $hours)
    {
        return static::query()->where('created_at', '<', now()->subHours($hours));
    }

    // Lighthouse-style letter. An N+1 drops it one step, so a fast request with an N+1 can't be an A.
    public static function gradeFor(float $ms, int $nPlusOne = 0): string
    {
        $letters = array_keys(config('memorylane.grades'));
        $index = count($letters); // F

        foreach (array_values(config('memorylane.grades')) as $i => $limit) {
            if ($ms < $limit) {
                $index = $i;
                break;
            }
        }

        $all = [...$letters, 'F'];

        return $all[min($index + ($nPlusOne > 0 ? 1 : 0), count($all) - 1)];
    }

    public static function gradeTone(string $grade): string
    {
        return match ($grade) {
            'A', 'B' => 'ok',
            'C' => 'warn',
            default => 'slow',
        };
    }

    public static function queryToneFor(float $ms): string
    {
        $slow = (float) config('memorylane.slow_query_ms');

        return $ms >= $slow ? 'slow' : ($ms >= $slow / 3 ? 'warn' : 'ok');
    }

    // "ok" / "warn" / "slow", for colouring durations on the dashboard.
    public static function toneFor(float $ms): string
    {
        $slow = (float) config('memorylane.always_record_slow');

        return match (true) {
            $ms >= $slow => 'slow',
            $ms >= $slow * 0.3 => 'warn',
            default => 'ok',
        };
    }
}
