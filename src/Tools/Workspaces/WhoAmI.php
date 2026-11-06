<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Domain\Workspace\Models\WorkspaceMembership;
use App\Models\User;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('whoami')]
#[Description('Who you are acting as in Larasan, every workspace you belong to, and which one is current. Call this first.')]
#[IsReadOnly]
final class WhoAmI extends LarasanTool
{
    protected function run(Request $request, User $actor): ResponseFactory
    {
        return $this->reply([
            'user' => ['id' => $actor->id, 'name' => $actor->name, 'email' => $actor->email],
            'current_workspace_id' => $actor->current_workspace_id,
            'workspaces' => self::workspacesOf($actor),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function workspacesOf(User $actor): array
    {
        return array_values(WorkspaceMembership::query()
            ->with('workspace')
            ->where('user_id', $actor->id)
            ->get()
            ->filter(fn (WorkspaceMembership $membership): bool => $membership->status->grantsAccess())
            ->map(fn (WorkspaceMembership $membership): array => Present::workspace($membership->workspace, $membership))
            ->sortBy('name')
            ->all());
    }
}
