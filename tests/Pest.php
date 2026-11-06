<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\ProjectMembership;
use App\Domain\Shared\Enums\ProjectAccessLevel;
use App\Domain\Shared\Enums\WorkspaceMembershipStatus;
use App\Domain\Shared\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Larasan\Mcp\Servers\LarasanServer;
use Laravel\Mcp\Server\Testing\PendingTestResponse;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

/*
 * These tests run inside a Larasan checkout, against its database and factories:
 * vendor/bin/pest --test-directory=vendor/hrnicek/larasan-mcp/tests
 */
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function mcpMember(Workspace $workspace, WorkspaceRole $role = WorkspaceRole::Member, ?User $user = null): User
{
    $user ??= User::factory()->create();

    WorkspaceMembership::factory()->withStatus(WorkspaceMembershipStatus::Active)->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    $user->forceFill(['current_workspace_id' => $user->current_workspace_id ?? $workspace->id])->save();

    return $user;
}

/**
 * @return array{0: Workspace, 1: User}
 */
function mcpWorkspace(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $workspace = Workspace::factory()->create();

    return [$workspace, mcpMember($workspace, $role)];
}

function mcpProject(Workspace $workspace, User $owner, bool $private = false): Project
{
    $project = Project::factory()->in($workspace)->ownedBy($owner)->create();

    if ($private) {
        $project->forceFill(['visibility' => 'private'])->save();
    }

    ProjectMembership::factory()->in($project)->forUser($owner)->withAccess(ProjectAccessLevel::Owner)->create();

    return $project;
}

/**
 * @param  class-string  $tool
 * @param  array<string, mixed>  $arguments
 */
function mcpCall(User $user, string $tool, array $arguments = []): TestResponse
{
    /** @var PendingTestResponse $pending */
    $pending = LarasanServer::actingAs($user);

    return $pending->tool($tool, $arguments);
}

/**
 * The structured payload of a successful tool call.
 *
 * @param  class-string  $tool
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function mcpData(User $user, string $tool, array $arguments = []): array
{
    $response = mcpCall($user, $tool, $arguments)->assertOk();

    $structured = [];
    $response->assertStructuredContent(function (AssertableJson $json) use (&$structured): void {
        $structured = $json->toArray();
        $json->etc();
    });

    return $structured;
}
