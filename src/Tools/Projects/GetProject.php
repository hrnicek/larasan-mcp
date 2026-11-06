<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Projects;

use App\Domain\Section\Models\Section;
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

#[Name('get_project')]
#[Description('Get one project with its sections (columns) in board order, and what you may do in it.')]
#[IsReadOnly]
final class GetProject extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $project = Find::project($actor, $request->string('project_id')->toString());

        $sections = $project->sections()
            ->orderBy('position')
            ->withCount('placements')
            ->get()
            ->map(fn (Section $section): array => [
                ...Present::section($section),
                'tasks_count' => (int) $section->getAttribute('placements_count'),
            ])
            ->all();

        return $this->reply([
            'project' => Present::project($project),
            'sections' => $sections,
            'can' => [
                'update' => $actor->can('update', $project),
                'create_task' => $actor->can('createTask', $project),
                'create_section' => $actor->can('createSection', $project),
                'archive' => $actor->can('archive', $project),
            ],
        ]);
    }
}
