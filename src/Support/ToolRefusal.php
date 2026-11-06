<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use RuntimeException;

/**
 * A request the tool declines, reported to the client as a tool error rather than a server fault.
 */
final class ToolRefusal extends RuntimeException
{
    /**
     * One sentence for "missing" and "not yours", so an id never confirms another tenant's records.
     */
    public static function notFound(string $thing): self
    {
        return new self("No {$thing} with that id is visible to you.");
    }

    public static function forbidden(string $action): self
    {
        return new self("You are not allowed to {$action}.");
    }
}
