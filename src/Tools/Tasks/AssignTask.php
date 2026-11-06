<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Task\Actions\AssignTask as AssignTaskAction;
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

#[Name('assign_task')]
#[Description('Assign a task to a workspace member (user id from list_members), or pass assignee_id=null to unassign it.')]
#[IsIdempotent]
final class AssignTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'assignee_id' => $schema->integer()->nullable()->required()->description('User id, or null to unassign.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        if ($actor->cannot('assign', $task)) {
            throw ToolRefusal::forbidden('assign this task');
        }

        $input = $request->validate(['assignee_id' => ['present', 'nullable', 'integer']]);
        $assignee = $input['assignee_id'] === null ? null : Find::member($task->workspace, (int) $input['assignee_id']);

        $task = app(AssignTaskAction::class)->handle($task, $actor, $assignee);

        return $this->reply(['task' => Present::taskSummary($task->load('assignee'))]);
    }
}
