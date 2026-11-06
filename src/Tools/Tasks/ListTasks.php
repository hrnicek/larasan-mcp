<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tasks;

use App\Domain\Placement\Models\TaskProjectMembership;
use App\Models\User;
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

#[Name('list_tasks')]
#[Description('List the tasks of a project in board order, grouped by section. Paginated.')]
#[IsReadOnly]
final class ListTasks extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'section_id' => $schema->string()->description('Only tasks in this section.'),
            'status' => $schema->string()->enum(['open', 'completed', 'all'])->default('open'),
            'page' => $schema->integer()->min(1)->default(1),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $project = Find::project($actor, $request->string('project_id')->toString());
        $input = $request->validate([
            'section_id' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,completed,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $section = isset($input['section_id']) ? Find::section($actor, $input['section_id']) : null;
        $status = $input['status'] ?? 'open';

        $page = TaskProjectMembership::query()
            ->select('task_project_memberships.*')
            ->with(['task.assignee', 'section'])
            ->leftJoin('sections', 'sections.id', '=', 'task_project_memberships.section_id')
            ->where('task_project_memberships.project_id', $project->id)
            ->when($section !== null, fn (Builder $query) => $query->where('task_project_memberships.section_id', $section?->id))
            ->whereHas('task', fn (Builder $task) => match ($status) {
                'open' => $task->whereNull('completed_at'),
                'completed' => $task->whereNotNull('completed_at'),
                default => $task,
            })
            ->orderByRaw('sections.position asc nulls first')
            ->orderBy('task_project_memberships.position')
            ->paginate((int) config('larasan-mcp.page_size'), page: (int) ($input['page'] ?? 1));

        return $this->reply([
            'project_id' => $project->id,
            'tasks' => $page->getCollection()
                ->map(fn (TaskProjectMembership $placement): array => [
                    ...Present::taskSummary($placement->task),
                    'section_id' => $placement->section_id,
                    'section_name' => $placement->section?->name,
                ])
                ->all(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }
}
