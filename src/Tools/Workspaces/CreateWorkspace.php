<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Domain\Workspace\Actions\CreateWorkspace as CreateWorkspaceAction;
use App\Domain\Workspace\Data\CreateWorkspaceData;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_workspace')]
#[Description('Create a new workspace. You become its owner.')]
final class CreateWorkspace extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Workspace name.')->required(),
            'slug' => $schema->string()->description('URL slug (lowercase, hyphens). Derived from the name when omitted.'),
            'timezone' => $schema->string()->description('IANA time zone, e.g. Europe/Prague. Defaults to UTC.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('workspaces', 'slug')],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        Throttle::hit('workspaces', $actor, 10);

        $workspace = app(CreateWorkspaceAction::class)->handle($actor, new CreateWorkspaceData(
            name: $input['name'],
            slug: $input['slug'] ?? null,
            timezone: $input['timezone'] ?? 'UTC',
        ));

        return $this->reply(['workspace' => Present::workspace($workspace, $workspace->membershipFor($actor))]);
    }
}
