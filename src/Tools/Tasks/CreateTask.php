<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Placement\Actions\CreateTaskInProject;
use App\Domain\Shared\Enums\TaskPriority;
use App\Domain\Task\Actions\CreateTask as CreateTaskAction;
use App\Domain\Task\Data\CreateTaskData;
use App\Domain\Task\Models\Task;
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

#[Name('create_task')]
#[Description('Create a task. Pass project_id (and optionally section_id) to put it on a project; without one it lands in the workspace\'s My Tasks only.')]
final class CreateTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'project_id' => $schema->string(),
            'section_id' => $schema->string()->description('Section of project_id to put the task in. Defaults to the ungrouped tasks.'),
            'workspace_id' => $schema->string()->description('Used only without project_id. Defaults to your current workspace.'),
            ...TaskFields::schema($schema),
            'assignee_id' => $schema->integer()->description('User id from list_members.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $input = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'string'],
            'section_id' => ['nullable', 'string'],
            'workspace_id' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:15000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'parent_id' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer'],
        ]);

        $project = isset($input['project_id']) ? Find::project($actor, $input['project_id']) : null;
        $workspace = $project->workspace ?? Find::workspace($actor, $input['workspace_id'] ?? null);
        $section = isset($input['section_id']) ? Find::section($actor, $input['section_id']) : null;

        if ($section !== null && $section->project_id !== $project?->id) {
            throw new ToolRefusal('That section is not in this project.');
        }

        if (isset($input['assignee_id'])) {
            Find::member($workspace, (int) $input['assignee_id']);
        }

        $parentId = isset($input['parent_id']) ? Find::task($actor, $input['parent_id'])->id : null;

        $data = new CreateTaskData(
            title: $input['title'],
            description: Markdown::toTaskHtml($input['description'] ?? null),
            priority: TaskPriority::tryFrom((string) ($input['priority'] ?? '')) ?? TaskPriority::Medium,
            dueAt: isset($input['due_at']) ? CarbonImmutable::parse($input['due_at']) : null,
            parentId: $parentId,
            assigneeId: isset($input['assignee_id']) ? (int) $input['assignee_id'] : null,
        );

        $task = $project === null
            ? app(CreateTaskAction::class)->handle($workspace, $actor, $data)
            : app(CreateTaskInProject::class)->handle($project, $actor, $data, $section)->task;

        return $this->reply(['task' => TaskDetail::for(Task::query()->findOrFail($task->id), $actor)]);
    }
}
