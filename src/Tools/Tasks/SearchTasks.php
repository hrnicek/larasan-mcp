<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Task\Models\Task;
use App\Domain\Task\Queries\ReachableTasks;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_tasks')]
#[Description('Find tasks across a workspace by text, assignee, project, status and due date. assignee="me" lists your own work. Paginated.')]
#[IsReadOnly]
final class SearchTasks extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
            'query' => $schema->string()->description('Text to look for in task titles.'),
            'assignee' => $schema->string()->description('"me", "none", or a user id.'),
            'project_id' => $schema->string(),
            'status' => $schema->string()->enum(['open', 'completed', 'all'])->default('open'),
            'due_before' => $schema->string()->format('date')->description('Due on or before this date (YYYY-MM-DD). Use today for overdue work.'),
            'due_after' => $schema->string()->format('date')->description('Due on or after this date (YYYY-MM-DD).'),
            'page' => $schema->integer()->min(1)->default(1),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);
        $input = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'assignee' => ['nullable', 'string', 'regex:/^(me|none|\d+)$/'],
            'project_id' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,completed,all'],
            'due_before' => ['nullable', 'date'],
            'due_after' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $project = isset($input['project_id']) ? Find::project($actor, $input['project_id']) : null;
        $assignee = $input['assignee'] ?? null;

        $tasks = app(ReachableTasks::class)
            ->constrain(Task::query(), $workspace, $actor)
            ->with('assignee')
            ->when(isset($input['query']), fn (Builder $query) => $query->whereLike('tasks.title', '%'.addcslashes((string) $input['query'], '%_\\').'%'))
            ->when($assignee === 'me', fn (Builder $query) => $query->where('tasks.assignee_id', $actor->id))
            ->when($assignee === 'none', fn (Builder $query) => $query->whereNull('tasks.assignee_id'))
            ->when(is_string($assignee) && ctype_digit($assignee), fn (Builder $query) => $query->where('tasks.assignee_id', (int) $assignee))
            ->when($project !== null, fn (Builder $query) => $query->whereHas('placements', fn (Builder $placements) => $placements->where('project_id', $project?->id)))
            ->when(($input['status'] ?? 'open') === 'open', fn (Builder $query) => $query->whereNull('tasks.completed_at'))
            ->when(($input['status'] ?? 'open') === 'completed', fn (Builder $query) => $query->whereNotNull('tasks.completed_at'))
            ->when(isset($input['due_before']), fn (Builder $query) => $query->where('tasks.due_at', '<=', CarbonImmutable::parse($input['due_before'])->endOfDay()))
            ->when(isset($input['due_after']), fn (Builder $query) => $query->where('tasks.due_at', '>=', CarbonImmutable::parse($input['due_after'])->startOfDay()))
            ->orderByRaw('tasks.due_at asc nulls last')
            ->orderByDesc('tasks.created_at')
            ->paginate((int) config('larasan-mcp.page_size'), ['tasks.*'], page: (int) ($input['page'] ?? 1));

        return $this->reply([
            'workspace_id' => $workspace->id,
            'tasks' => $tasks->getCollection()->map(fn (Task $task): array => Present::taskSummary($task))->all(),
            'page' => $tasks->currentPage(),
            'last_page' => $tasks->lastPage(),
            'total' => $tasks->total(),
        ]);
    }
}
