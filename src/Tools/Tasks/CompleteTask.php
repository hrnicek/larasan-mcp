<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Task\Actions\CompleteTask as CompleteTaskAction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('complete_task')]
#[Description('Mark a task done, or reopen it with completed=false.')]
#[IsIdempotent]
final class CompleteTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'completed' => $schema->boolean()->default(true),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());
        $action = app(CompleteTaskAction::class);

        $task = $request->boolean('completed', true)
            ? $action->complete($task, $actor)
            : $action->reopen($task, $actor);

        return $this->reply(['task' => Present::taskSummary($task->load('assignee'))]);
    }
}
