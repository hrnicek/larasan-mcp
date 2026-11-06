<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Larasan throttles comments and uploads per account; the MCP endpoint keeps the same budgets.
 */
final readonly class Throttle
{
    public static function hit(string $bucket, User $actor, int $perMinute): void
    {
        $key = "larasan-mcp:{$bucket}:{$actor->id}";

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw new ToolRefusal("Too many {$bucket} in a minute. Try again in ".RateLimiter::availableIn($key).' seconds.');
        }

        RateLimiter::hit($key);
    }
}
