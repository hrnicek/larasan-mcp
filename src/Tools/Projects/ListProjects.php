<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Projects;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Queries\VisibleProjectsForUser;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('List the projects you can see in a workspace.')]
#[IsReadOnly]
final class ListProjects extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
            'include_archived' => $schema->boolean()->default(false),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);

        $projects = app(VisibleProjectsForUser::class)($workspace, $actor, $request->boolean('include_archived'))
            ->sortBy('name')
            ->map(fn (Project $project): array => Present::project($project))
            ->values()
            ->all();

        return $this->reply(['workspace_id' => $workspace->id, 'projects' => $projects]);
    }
}
