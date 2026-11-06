<?php

declare(strict_types=1);

use App\Domain\Comment\Models\Comment;
use App\Domain\Section\Models\Section;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\Notification;
use Larasan\Mcp\Tools\Comments\UpdateComment;
use Larasan\Mcp\Tools\Projects\CreateProject;
use Larasan\Mcp\Tools\Tasks\MoveTask;
use Larasan\Mcp\Tools\Tasks\UpdateTask;
use Larasan\Mcp\Tools\Workspaces\CreateWorkspace;
use Larasan\Mcp\Tools\Workspaces\InviteMember;
use Laravel\Passport\Passport;

it('lets a client register only with a callback on an allowed site', function (string $redirect, int $status): void {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$redirect]])->assertStatus($status);
})->with([
    'claude.ai' => ['https://claude.ai/api/mcp/auth_callback', 201],
    'chatgpt.com' => ['https://chatgpt.com/connector_platform_oauth_redirect', 201],
    'a local client' => ['http://localhost:33418/callback', 201],
    'a phishing site' => ['https://evil.example/cb', 400],
    'a look-alike host' => ['https://claude.ai.evil.example/cb', 400],
]);

it('names the site the code is sent to on the consent screen', function (): void {
    [, $owner] = mcpWorkspace();
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Totally Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->json('client_id');

    $this->actingAs($owner)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 's',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('You will be sent back to:')
        ->assertSee('claude.ai');
});

it('refuses a Passport token that was not issued for MCP', function (): void {
    [, $owner] = mcpWorkspace();
    Passport::actingAs($owner, []);

    $this->postJson('/mcp/larasan', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});

it('keeps the web app\'s budget for each abusable write', function (string $tool, int $limit, Closure $arguments): void {
    Notification::fake();
    [$workspace, $owner] = mcpWorkspace();
    $prepared = $arguments($workspace, $owner);

    foreach (range(1, $limit) as $n) {
        mcpCall($owner, $tool, $prepared($n))->assertOk();
    }

    mcpCall($owner, $tool, $prepared($limit + 1))->assertHasErrors();
})->with([
    'invitations' => [InviteMember::class, 10, fn () => fn (int $n): array => ['email' => "person{$n}@example.com"]],
    'workspaces' => [CreateWorkspace::class, 10, fn () => fn (int $n): array => ['name' => "Workspace {$n}"]],
    'projects' => [CreateProject::class, 20, fn () => fn (int $n): array => ['name' => "Project {$n}"]],
    'moves' => [MoveTask::class, 60, function ($workspace, $owner): Closure {
        $project = mcpProject($workspace, $owner);
        $sections = [
            Section::factory()->create(['project_id' => $project->id, 'position' => 1])->id,
            Section::factory()->create(['project_id' => $project->id, 'position' => 2])->id,
        ];
        $task = Task::factory()->in($workspace)->create();

        return fn (int $n): array => ['task_id' => $task->id, 'project_id' => $project->id, 'section_id' => $sections[$n % 2]];
    }],
    'comment edits' => [UpdateComment::class, 30, function ($workspace, $owner): Closure {
        $task = Task::factory()->in($workspace)->create();
        $comment = Comment::factory()->create([
            'workspace_id' => $workspace->id,
            'commentable_type' => 'task',
            'commentable_id' => $task->id,
            'author_id' => $owner->id,
            'body' => 'Draft',
        ]);

        return fn (int $n): array => ['comment_id' => $comment->id, 'body' => "Edit {$n}"];
    }],
]);

it('refuses to make a task the subtask of one in another workspace', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    [$otherWorkspace] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();
    $foreign = Task::factory()->in($otherWorkspace)->create();

    mcpCall($owner, UpdateTask::class, ['task_id' => $task->id, 'parent_id' => $foreign->id])
        ->assertHasErrors(['No task with that id is visible to you.']);

    expect($task->refresh()->parent_id)->toBeNull();
});
