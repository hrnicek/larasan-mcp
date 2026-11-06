<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Section\Models\Section;
use App\Domain\Shared\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use Larasan\Mcp\Tools\Projects\ArchiveProject;
use Larasan\Mcp\Tools\Projects\CreateProject;
use Larasan\Mcp\Tools\Projects\GetProject;
use Larasan\Mcp\Tools\Projects\ListProjects;
use Larasan\Mcp\Tools\Projects\UpdateProject;
use Larasan\Mcp\Tools\Sections\CreateSection;
use Larasan\Mcp\Tools\Sections\DeleteSection;
use Larasan\Mcp\Tools\Sections\UpdateSection;

it('creates a project in the current workspace', function (): void {
    [$workspace, $owner] = mcpWorkspace();

    $data = mcpData($owner, CreateProject::class, [
        'name' => 'Website relaunch',
        'description' => 'New site',
        'color' => 'teal',
        'visibility' => 'private',
        'start_date' => '2026-11-01',
        'due_date' => '2026-12-01',
    ]);

    $project = Project::query()->findOrFail($data['project']['id']);
    expect($project->workspace_id)->toBe($workspace->id)
        ->and($project->color?->value)->toBe('teal')
        ->and($data['project']['visibility'])->toBe('private')
        ->and($data['project']['url'])->toContain($project->id);
});

it('refuses project creation to a guest', function (): void {
    [$workspace] = mcpWorkspace();
    $guest = mcpMember($workspace, WorkspaceRole::Guest);

    mcpCall($guest, CreateProject::class, ['name' => 'Nope'])->assertHasErrors();

    expect(Project::query()->count())->toBe(0);
});

it('rejects a due date before the start date', function (): void {
    [, $owner] = mcpWorkspace();

    mcpCall($owner, CreateProject::class, ['name' => 'P', 'start_date' => '2026-12-01', 'due_date' => '2026-11-01'])->assertHasErrors();
});

it('lists only projects the caller can see', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);
    $open = mcpProject($workspace, $owner);
    $private = mcpProject($workspace, $owner, private: true);
    mcpProject(Workspace::factory()->create(), mcpMember(Workspace::factory()->create()));

    $ids = array_column(mcpData($member, ListProjects::class)['projects'], 'id');

    expect($ids)->toBe([$open->id]);

    mcpCall($member, GetProject::class, ['project_id' => $private->id])
        ->assertHasErrors(['No project with that id is visible to you.']);
});

it('updates only the fields it is given', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);
    $project->forceFill(['description' => 'Keep me', 'color' => 'red'])->save();

    mcpData($owner, UpdateProject::class, ['project_id' => $project->id, 'name' => 'Renamed']);

    $project->refresh();
    expect($project->name)->toBe('Renamed')
        ->and($project->description)->toBe('Keep me')
        ->and($project->color?->value)->toBe('red');

    mcpData($owner, UpdateProject::class, ['project_id' => $project->id, 'description' => null]);

    expect($project->refresh()->description)->toBeNull();
});

it('lets a project member without management rights read but not change it', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);
    $project = mcpProject($workspace, $owner);

    mcpCall($member, UpdateProject::class, ['project_id' => $project->id, 'name' => 'X'])
        ->assertHasErrors(['You are not allowed to change this project.']);

    expect(mcpData($member, GetProject::class, ['project_id' => $project->id])['can']['update'])->toBeFalse();
});

it('archives and restores a project', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);

    expect(mcpData($owner, ArchiveProject::class, ['project_id' => $project->id])['project']['archived'])->toBeTrue()
        ->and(mcpData($owner, ArchiveProject::class, ['project_id' => $project->id, 'archived' => false])['project']['archived'])->toBeFalse();
});

it('manages sections', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $project = mcpProject($workspace, $owner);

    $todo = mcpData($owner, CreateSection::class, ['project_id' => $project->id, 'name' => 'To do'])['section'];
    mcpData($owner, CreateSection::class, ['project_id' => $project->id, 'name' => 'Done', 'color' => '#00aa00']);

    mcpData($owner, UpdateSection::class, ['section_id' => $todo['id'], 'name' => 'Backlog']);

    $sections = mcpData($owner, GetProject::class, ['project_id' => $project->id])['sections'];
    expect(array_column($sections, 'name'))->toBe(['Backlog', 'Done'])
        ->and($sections[1]['color'])->toBe('#00aa00');

    mcpData($owner, DeleteSection::class, ['section_id' => $todo['id']]);

    expect(Section::query()->whereKey($todo['id'])->exists())->toBeFalse();
});

it('keeps sections of another workspace out of reach', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    [$otherWorkspace, $stranger] = mcpWorkspace();
    $section = Section::factory()->create(['project_id' => mcpProject($otherWorkspace, $stranger)->id]);

    mcpCall($owner, DeleteSection::class, ['section_id' => $section->id])
        ->assertHasErrors(['No section with that id is visible to you.']);

    expect($section->fresh())->not->toBeNull();
});
