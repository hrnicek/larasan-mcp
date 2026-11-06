<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Placement\Actions\AttachTaskToProject;
use App\Domain\Placement\Actions\MoveTaskInProject;
use App\Domain\Placement\Models\TaskProjectMembership;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\TaskDetail;
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('move_task')]
#[Description('Put a task on a project, or move it to another section of a project it is already on (e.g. To do → Done on a board). A task can be on several projects; this adds, it never removes.')]
final class MoveTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'section_id' => $schema->string()->description('Target section in that project. Omit for the ungrouped tasks.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());
        $project = Find::project($actor, $request->string('project_id')->toString());
        $sectionId = $request->string('section_id')->value() ?: null;
        $section = $sectionId === null ? null : Find::section($actor, $sectionId);

        if ($section !== null && $section->project_id !== $project->id) {
            throw new ToolRefusal('That section is not in this project.');
        }

        Throttle::hit('moves', $actor, 60);

        $placement = TaskProjectMembership::query()
            ->where('task_id', $task->id)
            ->where('project_id', $project->id)
            ->first()
            ?? app(AttachTaskToProject::class)->handle($task, $project, $actor);

        if ($placement->section_id !== $section?->id) {
            app(MoveTaskInProject::class)->handle($placement->setRelation('project', $project), $actor, $section);
        }

        return $this->reply(['task' => TaskDetail::for($task->refresh(), $actor)]);
    }
}
