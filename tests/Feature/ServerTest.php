<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Larasan\Mcp\Servers\LarasanServer;
use Laravel\Passport\Passport;

function rpc(string $method, array $params = [], int $id = 1): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
}

it('turns away a request without an OAuth token and points at the authorization server', function (): void {
    $response = $this->postJson('/mcp/larasan', rpc('tools/list'));

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))->toContain('resource_metadata=');
});

it('publishes OAuth discovery metadata', function (): void {
    $this->getJson('/.well-known/oauth-protected-resource/mcp/larasan')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp/larasan'))
        ->assertJsonPath('scopes_supported', ['mcp:use']);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('code_challenge_methods_supported', ['S256']);
});

it('lets a client register itself for the authorization code flow', function (): void {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->assertCreated()->assertJsonStructure(['client_id']);
});

it('serves every tool to a client holding an access token', function (): void {
    [, $owner] = mcpWorkspace();
    Passport::actingAs($owner, ['mcp:use']);

    $this->postJson('/mcp/larasan', rpc('initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'test', 'version' => '1'],
    ]))->assertOk()->assertJsonPath('result.serverInfo.name', 'Larasan');

    $tools = [];
    $cursor = null;

    do {
        $page = $this->postJson('/mcp/larasan', rpc('tools/list', $cursor === null ? [] : ['cursor' => $cursor], id: 2))->assertOk();
        $tools = [...$tools, ...$page->json('result.tools')];
        $cursor = $page->json('result.nextCursor');
    } while ($cursor !== null);

    expect(array_column($tools, 'name'))->toHaveCount(35)
        ->toContain('create_workspace', 'create_task', 'move_task', 'add_comment', 'upload_attachment');

    $this->postJson('/mcp/larasan', rpc('tools/call', ['name' => 'whoami', 'arguments' => (object) []], id: 3))
        ->assertOk()
        ->assertJsonPath('result.structuredContent.user.id', $owner->id);
});

it('names every tool in snake case and marks reads and deletions for the client', function (): void {
    $tools = collect((new ReflectionClass(LarasanServer::class))->getDefaultProperties()['tools'])
        ->map(fn (string $tool) => app($tool)->toArray());

    expect($tools->pluck('name')->every(fn (string $name): bool => preg_match('/^[a-z]+(_[a-z]+)*$/', $name) === 1))->toBeTrue()
        ->and($tools->pluck('name')->duplicates())->toBeEmpty();

    $annotations = $tools->mapWithKeys(fn (array $tool): array => [$tool['name'] => (array) $tool['annotations']]);

    foreach (['get_task', 'search_tasks', 'list_projects', 'download_attachment'] as $read) {
        expect($annotations[$read]['readOnlyHint'] ?? false)->toBeTrue();
    }

    foreach (['delete_task', 'delete_section', 'delete_comment', 'delete_attachment'] as $deletion) {
        expect($annotations[$deletion]['destructiveHint'] ?? false)->toBeTrue();
    }
});

it('signs a person in through the whole OAuth 2.1 flow and serves them with the token', function (): void {
    [, $owner] = mcpWorkspace();
    $redirect = 'https://claude.ai/api/mcp/auth_callback';
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [$redirect],
    ])->json('client_id');
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $this->actingAs($owner)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'mcp:use',
            'state' => 'xyz',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Claude');

    $approval = $this->actingAs($owner)->post('/oauth/authorize', [
        'state' => 'xyz',
        'client_id' => $clientId,
        'auth_token' => session('authToken'),
    ]);

    parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'] ?? null)->toBe('xyz');

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'code_verifier' => $verifier,
        'code' => $query['code'],
    ])->assertOk()->json('access_token');

    Auth::forgetGuards();

    $this->withToken('not-a-token')
        ->postJson('/mcp/larasan', rpc('tools/call', ['name' => 'whoami', 'arguments' => (object) []]))
        ->assertUnauthorized();

    Auth::forgetGuards();

    $this->withToken($token)
        ->postJson('/mcp/larasan', rpc('tools/call', ['name' => 'whoami', 'arguments' => (object) []]))
        ->assertOk()
        ->assertJsonPath('result.structuredContent.user.id', $owner->id);
});
