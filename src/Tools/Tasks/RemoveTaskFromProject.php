<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Placement\Actions\DetachTaskFromProject;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('remove_task_from_project')]
#[Description('Take a task off one project. The task itself is kept, with its other projects.')]
#[IsDestructive]
final class RemoveTaskFromProject extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());
        $project = Find::project($actor, $request->string('project_id')->toString());

        app(DetachTaskFromProject::class)->handle($task, $project, $actor);

        return $this->reply(['removed' => true, 'task_id' => $task->id, 'project_id' => $project->id]);
    }
}
