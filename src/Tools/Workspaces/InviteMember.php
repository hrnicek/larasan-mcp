<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Domain\Shared\Enums\Capability;
use App\Domain\Shared\Enums\WorkspaceRole;
use App\Domain\Workspace\Actions\InviteWorkspaceMember;
use App\Domain\Workspace\Data\InviteWorkspaceMemberData;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('invite_member')]
#[Description('Invite someone to a workspace by e-mail. Larasan sends them an invitation. Owners and admins only.')]
#[IsOpenWorld]
final class InviteMember extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
            'email' => $schema->string()->format('email')->required(),
            'role' => $schema->string()->enum(['admin', 'member', 'guest'])->default('member'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);

        if ($actor->cannot(Capability::WorkspaceMembersManage->value, $workspace)) {
            throw ToolRefusal::forbidden('invite people to this workspace');
        }

        $input = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', Rule::enum(WorkspaceRole::class), Rule::notIn([WorkspaceRole::Owner->value])],
        ]);

        Throttle::hit('invitations', $actor, 10);

        $membership = app(InviteWorkspaceMember::class)->handle($workspace, $actor, new InviteWorkspaceMemberData(
            email: $input['email'],
            role: WorkspaceRole::from($input['role'] ?? WorkspaceRole::Member->value),
        ));

        return $this->reply([
            'invitation' => [
                'email' => $membership->email,
                'role' => $membership->role->value,
                'status' => $membership->status->value,
                'expires_at' => $membership->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
