<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Projects;

use App\Domain\Project\Actions\ArchiveProject as ArchiveProjectAction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('archive_project')]
#[Description('Archive a project, or restore an archived one with archived=false. Archiving hides it from the sidebar but keeps every task.')]
#[IsIdempotent]
final class ArchiveProject extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'archived' => $schema->boolean()->default(true),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $project = Find::project($actor, $request->string('project_id')->toString());

        if ($actor->cannot('archive', $project)) {
            throw ToolRefusal::forbidden('archive this project');
        }

        $archive = app(ArchiveProjectAction::class);
        $project = $request->boolean('archived', true)
            ? $archive->archive($project, $actor)
            : $archive->restore($project, $actor);

        return $this->reply(['project' => Present::project($project)]);
    }
}
