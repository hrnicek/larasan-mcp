<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Comment\Models\Comment;
use App\Domain\File\Models\Attachment;
use App\Domain\Project\Models\Project;
use App\Domain\Section\Models\Section;
use App\Domain\Tag\Models\Tag;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Resolves the records a tool names, refusing any the actor cannot see.
 */
final readonly class Find
{
    public static function workspace(User $actor, ?string $id): Workspace
    {
        $id ??= $actor->current_workspace_id;

        if ($id === null) {
            throw new ToolRefusal('Pass a workspace_id: you have no current workspace. Call list_workspaces to see yours.');
        }

        $workspace = self::isUuid($id) ? Workspace::query()->find($id) : null;

        if (! $workspace instanceof Workspace || $actor->cannot('view', $workspace)) {
            throw ToolRefusal::notFound('workspace');
        }

        return $workspace;
    }

    public static function project(User $actor, string $id): Project
    {
        $project = self::isUuid($id) ? Project::query()->with('workspace')->find($id) : null;

        if (! $project instanceof Project || $actor->cannot('view', $project)) {
            throw ToolRefusal::notFound('project');
        }

        return $project;
    }

    public static function section(User $actor, string $id): Section
    {
        $section = self::isUuid($id) ? Section::query()->with('project.workspace')->find($id) : null;

        if (! $section instanceof Section || $actor->cannot('view', $section->project)) {
            throw ToolRefusal::notFound('section');
        }

        return $section;
    }

    public static function task(User $actor, string $id): Task
    {
        $task = self::isUuid($id) ? Task::query()->with('workspace')->find($id) : null;

        if (! $task instanceof Task || $actor->cannot('view', $task)) {
            throw ToolRefusal::notFound('task');
        }

        return $task;
    }

    public static function comment(User $actor, string $id): Comment
    {
        $comment = self::isUuid($id) ? Comment::query()->find($id) : null;
        $subject = $comment?->commentable;

        if (! $comment instanceof Comment || ! $subject instanceof Task || $actor->cannot('view', $subject)) {
            throw ToolRefusal::notFound('comment');
        }

        return $comment;
    }

    public static function attachment(User $actor, string $id): Attachment
    {
        $attachment = self::isUuid($id) ? Attachment::query()->with('file')->find($id) : null;
        $subject = $attachment?->attachable;

        if (! $attachment instanceof Attachment || ! $subject instanceof Task || $actor->cannot('view', $subject)) {
            throw ToolRefusal::notFound('attachment');
        }

        return $attachment;
    }

    public static function tag(User $actor, string $id): Tag
    {
        $tag = self::isUuid($id) ? Tag::query()->find($id) : null;

        if (! $tag instanceof Tag) {
            throw ToolRefusal::notFound('tag');
        }

        $workspace = Workspace::query()->find($tag->workspace_id);

        if (! $workspace instanceof Workspace || $actor->cannot('view', $workspace)) {
            throw ToolRefusal::notFound('tag');
        }

        return $tag;
    }

    public static function member(Workspace $workspace, int $userId): User
    {
        if (! $workspace->hasActiveMember($userId)) {
            throw new ToolRefusal('That person is not an active member of this workspace. Call list_members to see who is.');
        }

        return User::query()->findOrFail($userId);
    }

    /**
     * PostgreSQL rejects a malformed uuid with a query error, so it is screened out first.
     */
    private static function isUuid(string $id): bool
    {
        return Str::isUuid($id);
    }
}
