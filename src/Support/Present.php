<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Comment\Models\Comment;
use App\Domain\File\Models\Attachment;
use App\Domain\Placement\Models\TaskProjectMembership;
use App\Domain\Project\Models\Project;
use App\Domain\Section\Models\Section;
use App\Domain\Shared\Html\RichText;
use App\Domain\Tag\Models\Tag;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMembership;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The shapes tools hand back to the model. Callers eager load what each shape reads.
 */
final readonly class Present
{
    /**
     * @return array<string, mixed>
     */
    public static function workspace(Workspace $workspace, ?WorkspaceMembership $membership = null): array
    {
        return array_filter([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'timezone' => $workspace->timezone,
            'your_role' => $membership?->role->value,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'workspace_id' => $project->workspace_id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'color' => $project->color?->value,
            'icon' => $project->icon?->value,
            'visibility' => $project->visibility->value,
            'default_view' => $project->default_view->value,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'archived' => $project->archived_at !== null,
            'url' => route('projects.show', $project),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function section(Section $section): array
    {
        return [
            'id' => $section->id,
            'project_id' => $section->project_id,
            'name' => $section->name,
            'color' => $section->color?->value,
            'position' => $section->position,
        ];
    }

    /**
     * Reads `assignee`.
     *
     * @return array<string, mixed>
     */
    public static function taskSummary(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'completed' => $task->completed_at !== null,
            'priority' => $task->priority->value,
            'due_at' => self::time($task->due_at),
            'assignee' => self::person($task->assignee),
            'parent_id' => $task->parent_id,
            'url' => route('tasks.show', $task),
        ];
    }

    /**
     * Reads `assignee`, `creator`, `placements.project`, `placements.section`, `tags`, `collaborators`,
     * `children.assignee` and `attachments.file`, and the `comments_count` aggregate.
     *
     * @return array<string, mixed>
     */
    public static function task(Task $task): array
    {
        return [
            ...self::taskSummary($task),
            'workspace_id' => $task->workspace_id,
            'description' => RichText::toPlainText($task->description),
            'description_html' => $task->description,
            'completed_at' => self::time($task->completed_at),
            'created_by' => self::person($task->creator),
            'created_at' => self::time($task->created_at),
            'projects' => $task->placements
                ->map(fn (TaskProjectMembership $placement): array => [
                    'project_id' => $placement->project_id,
                    'project_name' => $placement->project->name,
                    'section_id' => $placement->section_id,
                    'section_name' => $placement->section?->name,
                ])
                ->values()
                ->all(),
            'tags' => $task->tags->map(fn (Tag $tag): array => self::tag($tag))->values()->all(),
            'collaborators' => $task->collaborators->map(fn (User $user): ?array => self::person($user))->values()->all(),
            'subtasks' => $task->children->map(fn (Task $child): array => self::taskSummary($child))->values()->all(),
            'attachments' => $task->attachments->map(fn (Attachment $attachment): array => self::attachment($attachment))->values()->all(),
            'comments_count' => (int) $task->getAttribute('comments_count'),
        ];
    }

    /**
     * Reads `author`.
     *
     * @return array<string, mixed>
     */
    public static function comment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'task_id' => $comment->commentable_id,
            'author' => self::person($comment->author),
            'body' => $comment->body,
            'edited' => $comment->edited_at !== null,
            'created_at' => self::time($comment->created_at),
        ];
    }

    /**
     * Reads `file`.
     *
     * @return array<string, mixed>
     */
    public static function attachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->file->original_name,
            'mime_type' => $attachment->file->mime_type,
            'size_bytes' => (int) $attachment->file->size,
            'download_url' => route('attachments.download', $attachment),
            'created_at' => self::time($attachment->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function tag(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color?->value,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    public static function person(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }

    private static function time(?CarbonInterface $time): ?string
    {
        return $time?->toIso8601String();
    }
}
