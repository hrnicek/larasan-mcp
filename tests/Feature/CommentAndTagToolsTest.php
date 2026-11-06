<?php

declare(strict_types=1);

use App\Domain\Comment\Models\Comment;
use App\Domain\Tag\Models\Tag;
use App\Domain\Task\Models\Task;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Notification;
use Larasan\Mcp\Tools\Comments\AddComment;
use Larasan\Mcp\Tools\Comments\DeleteComment;
use Larasan\Mcp\Tools\Comments\ListComments;
use Larasan\Mcp\Tools\Comments\UpdateComment;
use Larasan\Mcp\Tools\Tags\ListTags;
use Larasan\Mcp\Tools\Tags\TagTask;
use Larasan\Mcp\Tools\Tags\UntagTask;

beforeEach(fn () => Notification::fake());

it('comments on a task and lists the thread', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $colleague = mcpMember($workspace);
    $task = Task::factory()->in($workspace)->create();

    mcpData($owner, AddComment::class, ['task_id' => $task->id, 'body' => 'First']);
    mcpData($colleague, AddComment::class, ['task_id' => $task->id, 'body' => "Ping @[{$owner->name}](user:{$owner->id})"]);

    $thread = mcpData($owner, ListComments::class, ['task_id' => $task->id]);

    expect(array_column($thread['comments'], 'body'))->toBe(['First', "Ping @[{$owner->name}](user:{$owner->id})"])
        ->and($thread['comments'][1]['author']['id'])->toBe($colleague->id);
});

it('lets only the author edit a comment', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $colleague = mcpMember($workspace);
    $task = Task::factory()->in($workspace)->create();
    $comment = mcpData($colleague, AddComment::class, ['task_id' => $task->id, 'body' => 'Mine'])['comment'];

    mcpCall($owner, UpdateComment::class, ['comment_id' => $comment['id'], 'body' => 'Not yours'])->assertHasErrors();
    $edited = mcpData($colleague, UpdateComment::class, ['comment_id' => $comment['id'], 'body' => 'Edited'])['comment'];

    expect($edited['body'])->toBe('Edited')->and($edited['edited'])->toBeTrue();
});

it('removes a comment', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $comment = mcpData($owner, AddComment::class, ['task_id' => $task->id, 'body' => 'Bye'])['comment'];

    mcpData($owner, DeleteComment::class, ['comment_id' => $comment['id']]);

    expect(Comment::query()->find($comment['id']))->toBeNull();
});

it('keeps comments of another workspace unreadable and unwritable', function (): void {
    [, $owner] = mcpWorkspace();
    [$otherWorkspace, $stranger] = mcpWorkspace();
    $task = Task::factory()->in($otherWorkspace)->create();
    $comment = mcpData($stranger, AddComment::class, ['task_id' => $task->id, 'body' => 'Private'])['comment'];

    mcpCall($owner, ListComments::class, ['task_id' => $task->id])->assertHasErrors()->assertDontSee('Private');
    mcpCall($owner, AddComment::class, ['task_id' => $task->id, 'body' => 'Hi'])->assertHasErrors();
    mcpCall($owner, DeleteComment::class, ['comment_id' => $comment['id']])->assertHasErrors(['No comment with that id is visible to you.']);

    expect(Comment::query()->count())->toBe(1);
});

it('throttles comments like the web app does', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();

    foreach (range(1, 30) as $n) {
        mcpCall($owner, AddComment::class, ['task_id' => $task->id, 'body' => "#{$n}"])->assertOk();
    }

    mcpCall($owner, AddComment::class, ['task_id' => $task->id, 'body' => 'one too many'])->assertHasErrors();
});

it('tags a task by name, reusing an existing tag, and untags it', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $existing = Tag::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Bug']);

    $reused = mcpData($owner, TagTask::class, ['task_id' => $task->id, 'name' => 'bug'])['tag'];
    $created = mcpData($owner, TagTask::class, ['task_id' => $task->id, 'name' => 'Design', 'color' => 'violet'])['tag'];

    expect($reused['id'])->toBe($existing->id)
        ->and(array_column(mcpData($owner, ListTags::class)['tags'], 'name'))->toBe(['Bug', 'Design'])
        ->and($task->tags()->count())->toBe(2);

    mcpData($owner, UntagTask::class, ['task_id' => $task->id, 'tag_id' => $created['id']]);

    expect($task->tags()->pluck('tags.id')->all())->toBe([$existing->id]);
});

it('refuses a tag from another workspace', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $foreign = Tag::factory()->create(['workspace_id' => Workspace::factory()->create()->id]);

    mcpCall($owner, TagTask::class, ['task_id' => $task->id, 'tag_id' => $foreign->id])
        ->assertHasErrors(['No tag with that id is visible to you.']);
});
