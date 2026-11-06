<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Placement\Models\TaskProjectMembership;
use App\Domain\Task\Models\Task;
use App\Models\User;

/**
 * Loads what `Present::task()` reads, keeping out projects and subtasks the actor cannot see.
 */
final readonly class TaskDetail
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Task $task, User $actor): array
    {
        $task->load([
            'assignee',
            'creator',
            'placements.project.workspace',
            'placements.section',
            'tags',
            'collaborators',
            'children' => fn ($query) => $query->with(['assignee', 'workspace'])->orderBy('created_at'),
            'attachments' => fn ($query) => $query->with('file')->orderBy('position'),
        ])->loadCount('comments');

        // A task can sit in a private project the actor is not in; its name must not leak through the task.
        $task->setRelation('placements', $task->placements->filter(
            fn (TaskProjectMembership $placement): bool => $actor->can('view', $placement->project),
        ));

        $task->setRelation('children', $task->children->filter(
            fn (Task $child): bool => $actor->can('view', $child),
        ));

        return Present::task($task);
    }
}
