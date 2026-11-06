<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Domain\Shared\Enums\Capability;
use App\Domain\Shared\Enums\WorkspaceMembershipStatus;
use App\Domain\Workspace\Models\WorkspaceMembership;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_members')]
#[Description('List the active members of a workspace with their user ids and roles. Use the ids to assign tasks or mention people.')]
#[IsReadOnly]
final class ListMembers extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);
        $showsEmail = $actor->can(Capability::WorkspaceMembersManage->value, $workspace);

        $members = WorkspaceMembership::query()
            ->with('user')
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceMembershipStatus::Active->value)
            ->whereNotNull('user_id')
            ->get()
            ->map(fn (WorkspaceMembership $membership): ?array => $membership->user === null ? null : array_filter([
                'user_id' => $membership->user->id,
                'name' => $membership->user->name,
                'email' => $showsEmail ? $membership->user->email : null,
                'role' => $membership->role->value,
                'mention' => "@[{$membership->user->name}](user:{$membership->user->id})",
            ], fn (mixed $value): bool => $value !== null))
            ->filter()
            ->sortBy('name')
            ->values()
            ->all();

        return $this->reply(['workspace_id' => $workspace->id, 'members' => $members]);
    }
}
