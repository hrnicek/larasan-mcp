<?php

declare(strict_types=1);

use App\Domain\Placement\Actions\AttachTaskToProject;
use App\Domain\Placement\Models\TaskProjectMembership;
use App\Domain\Section\Models\Section;
use App\Domain\Shared\Enums\WorkspaceRole;
use App\Domain\Task\Models\Task;
use Illuminate\Support\Facades\Notification;
use Larasan\Mcp\Support\Markdown;
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

beforeEach(fn () => Notification::fake());

it('creates a task in a project section with a Markdown description', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $section = Section::factory()->create(['project_id' => $project->id, 'position' => 1]);
    $assignee = mcpMember($workspace);

    $data = mcpData($owner, CreateTask::class, [
        'title' => 'Write copy',
        'project_id' => $project->id,
        'section_id' => $section->id,
        'description' => "Intro **bold**\n\n- one\n- two\n\n<script>alert(1)</script>",
        'priority' => 'high',
        'due_at' => '2026-11-20',
        'assignee_id' => $assignee->id,
    ])['task'];

    $task = Task::query()->findOrFail($data['id']);
    expect($task->priority->value)->toBe('high')
        ->and($task->assignee_id)->toBe($assignee->id)
        ->and($task->description)->toContain('<strong>bold</strong>')
        ->and($task->description)->toContain('<li data-list="bullet">one</li>')
        ->and($task->description)->not->toContain('<script')
        ->and($data['projects'][0]['section_id'])->toBe($section->id);
});

it('creates a workspace task without a project', function (): void {
    [$workspace, $owner] = mcpWorkspace();

    $data = mcpData($owner, CreateTask::class, ['title' => 'Loose end'])['task'];

    expect(Task::query()->findOrFail($data['id'])->workspace_id)->toBe($workspace->id)
        ->and($data['projects'])->toBe([]);
});

it('refuses an assignee from outside the workspace and a section of another project', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $otherSection = Section::factory()->create(['project_id' => mcpProject($workspace, $owner)->id]);
    [, $stranger] = mcpWorkspace();

    mcpCall($owner, CreateTask::class, ['title' => 'T', 'assignee_id' => $stranger->id])->assertHasErrors();
    mcpCall($owner, CreateTask::class, ['title' => 'T', 'project_id' => $project->id, 'section_id' => $otherSection->id])
        ->assertHasErrors(['That section is not in this project.']);

    expect(Task::query()->count())->toBe(0);
});

it('refuses a parent task the caller cannot reach', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    [$otherWorkspace] = mcpWorkspace();
    $foreign = Task::factory()->in($otherWorkspace)->create();

    mcpCall($owner, CreateTask::class, ['title' => 'Sub', 'parent_id' => $foreign->id])
        ->assertHasErrors(['No task with that id is visible to you.']);
});

it('hides tasks of another workspace behind the not-found message', function (): void {
    [, $owner] = mcpWorkspace();
    [$otherWorkspace] = mcpWorkspace();
    $foreign = Task::factory()->in($otherWorkspace)->create(['title' => 'Secret']);

    foreach ([GetTask::class, CompleteTask::class, DeleteTask::class] as $tool) {
        mcpCall($owner, $tool, ['task_id' => $foreign->id])
            ->assertHasErrors(['No task with that id is visible to you.'])
            ->assertDontSee('Secret');
    }

    expect($foreign->fresh()?->completed_at)->toBeNull();
});

it('keeps a task in a private project from the members outside it', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);
    $private = mcpProject($workspace, $owner, private: true);
    $task = Task::factory()->in($workspace)->create();
    app(AttachTaskToProject::class)->handle($task, $private, $owner);

    mcpCall($member, GetTask::class, ['task_id' => $task->id])->assertHasErrors();
    expect(mcpData($member, SearchTasks::class)['tasks'])->toBe([]);
});

it('does not name a private project through a task that is also on a public one', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);
    $public = mcpProject($workspace, $owner);
    $private = mcpProject($workspace, $owner, private: true);
    $private->forceFill(['name' => 'Acquisition talks'])->save();
    $task = Task::factory()->in($workspace)->create();
    app(AttachTaskToProject::class)->handle($task, $public, $owner);
    app(AttachTaskToProject::class)->handle($task, $private, $owner);

    $data = mcpData($member, GetTask::class, ['task_id' => $task->id]);

    expect(array_column($data['task']['projects'], 'project_id'))->toBe([$public->id]);
    mcpCall($member, GetTask::class, ['task_id' => $task->id])->assertDontSee('Acquisition talks');
});

it('searches by text, assignee and due date', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $mine = Task::factory()->in($workspace)->assignedTo($owner)->create(['title' => 'Invoice ACME', 'due_at' => '2026-11-10 12:00']);
    Task::factory()->in($workspace)->create(['title' => 'Invoice Globex', 'due_at' => '2026-12-10 12:00']);
    Task::factory()->in($workspace)->completed()->create(['title' => 'Invoice old']);

    $ids = fn (array $arguments): array => array_column(mcpData($owner, SearchTasks::class, $arguments)['tasks'], 'id');

    expect($ids(['query' => 'invoice']))->toHaveCount(2)
        ->and($ids(['assignee' => 'me']))->toBe([$mine->id])
        ->and($ids(['due_before' => '2026-11-30']))->toBe([$mine->id])
        ->and($ids(['query' => 'invoice', 'status' => 'all']))->toHaveCount(3);
});

it('lists a project board in order and filters by section', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $done = Section::factory()->create(['project_id' => $project->id, 'name' => 'Done', 'position' => 1]);

    $first = mcpData($owner, CreateTask::class, ['title' => 'Ungrouped', 'project_id' => $project->id])['task'];
    $second = mcpData($owner, CreateTask::class, ['title' => 'Shipped', 'project_id' => $project->id, 'section_id' => $done->id])['task'];

    $all = mcpData($owner, ListTasks::class, ['project_id' => $project->id]);
    $onlyDone = mcpData($owner, ListTasks::class, ['project_id' => $project->id, 'section_id' => $done->id]);

    expect(array_column($all['tasks'], 'id'))->toBe([$first['id'], $second['id']])
        ->and($all['total'])->toBe(2)
        ->and(array_column($onlyDone['tasks'], 'id'))->toBe([$second['id']]);
});

it('updates only the fields it is given and clears what is set to null', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create(['title' => 'Old', 'description' => '<p>Keep</p>', 'due_at' => '2026-11-10 12:00']);

    mcpData($owner, UpdateTask::class, ['task_id' => $task->id, 'title' => 'New']);
    expect($task->refresh()->title)->toBe('New')->and($task->description)->toBe('<p>Keep</p>');

    mcpData($owner, UpdateTask::class, ['task_id' => $task->id, 'due_at' => null, 'priority' => 'urgent']);
    expect($task->refresh()->due_at)->toBeNull()->and($task->priority->value)->toBe('urgent');

    mcpCall($owner, UpdateTask::class, ['task_id' => $task->id])->assertHasErrors();
    mcpCall($owner, UpdateTask::class, ['task_id' => $task->id, 'parent_id' => $task->id])->assertHasErrors();
});

it('refuses a guest editing a task', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $guest = mcpMember($workspace, WorkspaceRole::Guest);
    $task = Task::factory()->in($workspace)->create(['title' => 'Untouched']);
    app(AttachTaskToProject::class)->handle($task, $project, $owner);

    mcpCall($guest, UpdateTask::class, ['task_id' => $task->id, 'title' => 'Changed'])->assertHasErrors();

    expect($task->refresh()->title)->toBe('Untouched');
});

it('completes, reopens, assigns and unassigns', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $colleague = mcpMember($workspace);
    $task = Task::factory()->in($workspace)->create();

    expect(mcpData($owner, CompleteTask::class, ['task_id' => $task->id])['task']['completed'])->toBeTrue()
        ->and(mcpData($owner, CompleteTask::class, ['task_id' => $task->id, 'completed' => false])['task']['completed'])->toBeFalse()
        ->and(mcpData($owner, AssignTask::class, ['task_id' => $task->id, 'assignee_id' => $colleague->id])['task']['assignee']['id'])->toBe($colleague->id)
        ->and(mcpData($owner, AssignTask::class, ['task_id' => $task->id, 'assignee_id' => null])['task']['assignee'])->toBeNull();

    [, $stranger] = mcpWorkspace();
    mcpCall($owner, AssignTask::class, ['task_id' => $task->id, 'assignee_id' => $stranger->id])->assertHasErrors();
});

it('places a task on a project, moves it between sections and takes it off', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $doing = Section::factory()->create(['project_id' => $project->id, 'position' => 1]);
    $done = Section::factory()->create(['project_id' => $project->id, 'position' => 2]);
    $task = Task::factory()->in($workspace)->create();

    mcpData($owner, MoveTask::class, ['task_id' => $task->id, 'project_id' => $project->id, 'section_id' => $doing->id]);
    mcpData($owner, MoveTask::class, ['task_id' => $task->id, 'project_id' => $project->id, 'section_id' => $done->id]);

    $placement = TaskProjectMembership::query()->where('task_id', $task->id)->sole();
    expect($placement->section_id)->toBe($done->id);

    mcpData($owner, RemoveTaskFromProject::class, ['task_id' => $task->id, 'project_id' => $project->id]);

    expect(TaskProjectMembership::query()->where('task_id', $task->id)->exists())->toBeFalse()
        ->and($task->fresh())->not->toBeNull();
});

it('deletes a task', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $task = Task::factory()->in($workspace)->create();

    mcpData($owner, DeleteTask::class, ['task_id' => $task->id]);

    expect(Task::query()->find($task->id))->toBeNull();
});

it('stores Markdown lists the way the editor reads them', function (): void {
    expect(Markdown::toTaskHtml("1. first\n2. second\n\n- dot"))
        ->toContain('<ol><li data-list="ordered">first</li>')
        ->toContain('<li data-list="bullet">dot</li>')
        ->not->toContain('<ul>')
        ->and(Markdown::toTaskHtml('   '))->toBeNull()
        ->and(Markdown::toTaskHtml('[x](javascript:alert(1))'))->not->toContain('javascript:');
});
