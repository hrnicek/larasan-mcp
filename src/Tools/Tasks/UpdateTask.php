<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Shared\Enums\TaskPriority;
use App\Domain\Task\Actions\UpdateTask as UpdateTaskAction;
use App\Domain\Task\Data\UpdateTaskData;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Markdown;
use Larasan\Mcp\Support\TaskDetail;
use Larasan\Mcp\Support\TaskFields;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_task')]
#[Description('Change a task\'s title, description, priority, due date or parent. Only the fields you pass change; pass null to clear description, due_at or parent_id. The description replaces the old one entirely.')]
#[IsIdempotent]
final class UpdateTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'title' => $schema->string(),
            ...TaskFields::schema($schema),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        if ($actor->cannot('update', $task)) {
            throw ToolRefusal::forbidden('change this task');
        }

        $input = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:15000'],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'parent_id' => ['sometimes', 'nullable', 'string', Rule::notIn([$task->id])],
        ]);

        $fields = array_values(array_intersect(UpdateTaskData::EVERY_FIELD, array_keys($input)));

        if ($fields === []) {
            throw new ToolRefusal('Pass at least one of: title, description, priority, due_at, parent_id.');
        }

        $parentId = isset($input['parent_id']) ? Find::task($actor, $input['parent_id'])->id : null;

        app(UpdateTaskAction::class)->handle($task, $actor, new UpdateTaskData(
            title: $input['title'] ?? null,
            description: Markdown::toTaskHtml($input['description'] ?? null),
            priority: isset($input['priority']) ? TaskPriority::from($input['priority']) : null,
            dueAt: isset($input['due_at']) ? CarbonImmutable::parse($input['due_at']) : null,
            parentId: $parentId,
            fields: $fields,
        ));

        return $this->reply(['task' => TaskDetail::for($task->refresh(), $actor)]);
    }
}
