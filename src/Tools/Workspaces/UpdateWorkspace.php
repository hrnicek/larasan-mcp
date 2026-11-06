<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Domain\Workspace\Actions\UpdateWorkspace as UpdateWorkspaceAction;
use App\Domain\Workspace\Data\UpdateWorkspaceData;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_workspace')]
#[Description('Rename a workspace or change its slug or time zone. Owners and admins only. Omitted fields stay as they are.')]
#[IsIdempotent]
final class UpdateWorkspace extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'timezone' => $schema->string()->description('IANA time zone.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);

        if ($actor->cannot('update', $workspace)) {
            throw ToolRefusal::forbidden('change this workspace');
        }

        $input = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('workspaces', 'slug')->ignore($workspace->id)],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
        ]);

        $workspace = app(UpdateWorkspaceAction::class)->handle($workspace, new UpdateWorkspaceData(
            name: $input['name'] ?? $workspace->name,
            slug: $input['slug'] ?? null,
            timezone: $input['timezone'] ?? null,
        ));

        return $this->reply(['workspace' => Present::workspace($workspace, $workspace->membershipFor($actor))]);
    }
}
