<?php

declare(strict_types=1);

namespace Larasan\Mcp\Servers;

use Larasan\Mcp\Tools\Attachments\DeleteAttachment;
use Larasan\Mcp\Tools\Attachments\DownloadAttachment;
use Larasan\Mcp\Tools\Attachments\ListAttachments;
use Larasan\Mcp\Tools\Attachments\UploadAttachment;
use Larasan\Mcp\Tools\Comments\AddComment;
use Larasan\Mcp\Tools\Comments\DeleteComment;
use Larasan\Mcp\Tools\Comments\ListComments;
use Larasan\Mcp\Tools\Comments\UpdateComment;
use Larasan\Mcp\Tools\Projects\ArchiveProject;
use Larasan\Mcp\Tools\Projects\CreateProject;
use Larasan\Mcp\Tools\Projects\GetProject;
use Larasan\Mcp\Tools\Projects\ListProjects;
use Larasan\Mcp\Tools\Projects\UpdateProject;
use Larasan\Mcp\Tools\Sections\CreateSection;
use Larasan\Mcp\Tools\Sections\DeleteSection;
use Larasan\Mcp\Tools\Sections\UpdateSection;
use Larasan\Mcp\Tools\Tags\ListTags;
use Larasan\Mcp\Tools\Tags\TagTask;
use Larasan\Mcp\Tools\Tags\UntagTask;
use Larasan\Mcp\Tools\Tasks\AssignTask;
use Larasan\Mcp\Tools\Tasks\CompleteTask;
use Larasan\Mcp\Tools\Tasks\CreateTask;
use Larasan\Mcp\Tools\Tasks\DeleteTask;
use Larasan\Mcp\Tools\Tasks\GetTask;
use Larasan\Mcp\Tools\Tasks\ListTasks;
use Larasan\Mcp\Tools\Tasks\MoveTask;
use Larasan\Mcp\Tools\Tasks\RemoveTaskFromProject;
use Larasan\Mcp\Tools\Tasks\SearchTasks;
use Larasan\Mcp\Tools\Tasks\UpdateTask;
use Larasan\Mcp\Tools\Workspaces\CreateWorkspace;
use Larasan\Mcp\Tools\Workspaces\InviteMember;
use Larasan\Mcp\Tools\Workspaces\ListMembers;
use Larasan\Mcp\Tools\Workspaces\ListWorkspaces;
use Larasan\Mcp\Tools\Workspaces\UpdateWorkspace;
use Larasan\Mcp\Tools\Workspaces\WhoAmI;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Larasan')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Larasan is a project manager. Work is organised as workspace → projects → sections (board columns) → tasks,
    and a task carries a description, an assignee, collaborators, tags, subtasks, comments and attachments.

    - You act as the signed-in person, with exactly their permissions. A refusal means they may not do it.
    - Start with `whoami` to learn the workspaces and the current one. Tools that take an optional `workspace_id`
      use the current workspace when it is omitted.
    - Ids are UUIDs, except people, who have integer ids (`list_members`).
    - Look before you write: find tasks with `search_tasks` or `list_tasks`, read them with `get_task`.
    - To move a card on a board, use `move_task` with the target `section_id`.
    - Task descriptions take Markdown. Comments are plain text; mention someone with `@[Name](user:id)`.
    - Ask the user before `delete_task`, `delete_section`, `delete_comment` or `delete_attachment`.
    MARKDOWN)]
class LarasanServer extends Server
{
    protected array $tools = [
        WhoAmI::class,
        ListWorkspaces::class,
        CreateWorkspace::class,
        UpdateWorkspace::class,
        ListMembers::class,
        InviteMember::class,

        ListProjects::class,
        GetProject::class,
        CreateProject::class,
        UpdateProject::class,
        ArchiveProject::class,

        CreateSection::class,
        UpdateSection::class,
        DeleteSection::class,

        SearchTasks::class,
        ListTasks::class,
        GetTask::class,
        CreateTask::class,
        UpdateTask::class,
        CompleteTask::class,
        AssignTask::class,
        MoveTask::class,
        RemoveTaskFromProject::class,
        DeleteTask::class,

        ListTags::class,
        TagTask::class,
        UntagTask::class,

        ListComments::class,
        AddComment::class,
        UpdateComment::class,
        DeleteComment::class,

        ListAttachments::class,
        UploadAttachment::class,
        DownloadAttachment::class,
        DeleteAttachment::class,
    ];
}
