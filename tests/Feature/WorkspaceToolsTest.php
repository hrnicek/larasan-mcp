<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\WorkspaceRole;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Larasan\Mcp\Tools\Workspaces\CreateWorkspace;
use Larasan\Mcp\Tools\Workspaces\InviteMember;
use Larasan\Mcp\Tools\Workspaces\ListMembers;
use Larasan\Mcp\Tools\Workspaces\UpdateWorkspace;
use Larasan\Mcp\Tools\Workspaces\WhoAmI;

it('tells the client who it acts as and where', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    Workspace::factory()->create(['name' => 'Somebody else']);

    $data = mcpData($owner, WhoAmI::class);

    expect($data['user']['id'])->toBe($owner->id)
        ->and($data['current_workspace_id'])->toBe($workspace->id)
        ->and(array_column($data['workspaces'], 'id'))->toBe([$workspace->id])
        ->and($data['workspaces'][0]['your_role'])->toBe('owner');
});

it('creates a workspace owned by the caller', function (): void {
    [, $user] = mcpWorkspace(WorkspaceRole::Member);

    $data = mcpData($user, CreateWorkspace::class, ['name' => 'Acme', 'timezone' => 'Europe/Prague']);

    $workspace = Workspace::query()->findOrFail($data['workspace']['id']);
    expect($workspace->name)->toBe('Acme')
        ->and($workspace->timezone)->toBe('Europe/Prague')
        ->and($workspace->membershipFor($user)?->role)->toBe(WorkspaceRole::Owner);
});

it('validates workspace input', function (): void {
    [, $user] = mcpWorkspace();

    mcpCall($user, CreateWorkspace::class, ['name' => 'X', 'timezone' => 'Mars/Olympus'])->assertHasErrors();
});

it('lets an owner rename a workspace but not a member', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);

    mcpCall($member, UpdateWorkspace::class, ['name' => 'Hijacked'])->assertHasErrors(['You are not allowed to change this workspace.']);
    mcpData($owner, UpdateWorkspace::class, ['name' => 'Renamed']);

    expect($workspace->refresh()->name)->toBe('Renamed');
});

it('refuses a workspace the caller is not in, with the same words as a missing one', function (): void {
    [, $user] = mcpWorkspace();
    $foreign = Workspace::factory()->create();

    mcpCall($user, ListMembers::class, ['workspace_id' => $foreign->id])
        ->assertHasErrors(['No workspace with that id is visible to you.']);
    mcpCall($user, ListMembers::class, ['workspace_id' => (string) Str::uuid7()])
        ->assertHasErrors(['No workspace with that id is visible to you.']);
    mcpCall($user, ListMembers::class, ['workspace_id' => 'not-a-uuid'])
        ->assertHasErrors(['No workspace with that id is visible to you.']);
});

it('shows member e-mails only to people who manage members', function (): void {
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);

    $asOwner = mcpData($owner, ListMembers::class);
    $asMember = mcpData($member, ListMembers::class);

    expect($asOwner['members'])->toHaveCount(2)
        ->and(array_column($asOwner['members'], 'email'))->toContain($member->email)
        ->and(array_column($asMember['members'], 'email'))->toBe([])
        ->and($asMember['members'][0]['mention'])->toStartWith('@[');
});

it('invites by e-mail when the caller manages members', function (): void {
    Notification::fake();
    [$workspace, $owner] = mcpWorkspace();
    $member = mcpMember($workspace);

    mcpCall($member, InviteMember::class, ['email' => 'new@example.com'])->assertHasErrors();

    $data = mcpData($owner, InviteMember::class, ['email' => 'New@Example.com', 'role' => 'admin']);

    expect($data['invitation']['email'])->toBe('new@example.com')
        ->and($data['invitation']['role'])->toBe('admin');

    mcpCall($owner, InviteMember::class, ['email' => 'x@example.com', 'role' => 'owner'])->assertHasErrors();
});
