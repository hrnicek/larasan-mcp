<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\TaskDetail;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_task')]
#[Description('Get a task in full: description, projects and sections, tags, collaborators, subtasks, attachments and comment count.')]
#[IsReadOnly]
final class GetTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        return $this->reply([
            'task' => TaskDetail::for($task, $actor),
            'can' => [
                'update' => $actor->can('update', $task),
                'complete' => $actor->can('complete', $task),
                'assign' => $actor->can('assign', $task),
                'delete' => $actor->can('delete', $task),
                'comment' => $actor->can('comment', $task),
                'attach' => $actor->can('attach', $task),
            ],
        ]);
    }
}
