<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Task\Actions\DeleteTask as DeleteTaskAction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_task')]
#[Description('Delete a task, with its subtasks that are on no project of their own. Confirm with the user first.')]
#[IsDestructive]
final class DeleteTask extends LarasanTool
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

        app(DeleteTaskAction::class)->handle($task, $actor);

        return $this->reply(['deleted' => true, 'task_id' => $task->id]);
    }
}
