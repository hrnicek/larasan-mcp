# Larasan MCP

An [MCP](https://modelcontextprotocol.io) server for [Larasan](https://github.com/hrnicek/larasan), the open-source project manager. Connect ChatGPT, Claude, Cursor or any MCP client and run Larasan in plain language: create workspaces and projects, plan sections, write and move tasks, comment, and attach files.

> "Create a *Website relaunch* project with To do / Doing / Done, add a task to write the homepage copy for Petra due Friday, and attach this brief."

Every call acts as the person who signed in, through Larasan's own Actions and policies. The model can do exactly what that person can do in the web app — nothing more.

## How it works

- **Built on [laravel/mcp](https://github.com/laravel/mcp)**, served over streamable HTTP at `/mcp/larasan`.
- **Sign-in is OAuth 2.1 only**, via [Laravel Passport](https://laravel.com/docs/passport): discovery metadata, dynamic client registration and PKCE, which is what ChatGPT connectors and claude.ai expect. There is no API-key or stdio shortcut.
- **No duplicated rules.** Tools call Larasan's domain Actions (`CreateTask`, `MoveTaskInProject`, `AttachFile`, ...) and its policies. Workspace isolation, private projects, guest limits, mention resolution, rich-text sanitising and notifications all behave as they do in the UI.
- **Ids never leak.** An id from another workspace, a private project you are not in, or a malformed id all get the same "not visible to you" answer.

## Tools

| Area | Tools |
| --- | --- |
| Workspaces | `whoami`, `list_workspaces`, `create_workspace`, `update_workspace`, `list_members`, `invite_member` |
| Projects | `list_projects`, `get_project`, `create_project`, `update_project`, `archive_project` |
| Sections | `create_section`, `update_section`, `delete_section` |
| Tasks | `search_tasks`, `list_tasks`, `get_task`, `create_task`, `update_task`, `complete_task`, `assign_task`, `move_task`, `remove_task_from_project`, `delete_task` |
| Tags | `list_tags`, `tag_task`, `untag_task` |
| Comments | `list_comments`, `add_comment`, `update_comment`, `delete_comment` |
| Attachments | `list_attachments`, `upload_attachment`, `download_attachment`, `delete_attachment` |

Read-only tools are annotated `readOnlyHint`, deletions `destructiveHint`, so clients can ask before they run them. Task descriptions take Markdown and are stored as Larasan rich text. Comments are plain text; `@[Name](user:id)` mentions notify the person. Files travel as base64, within Larasan's `attachments` limits (types and size).

## Requirements

- Larasan on PHP 8.4, Laravel 13
- `laravel/mcp` ^0.9 and `laravel/passport` ^13 (installed with the package)
- HTTPS in production — OAuth clients refuse plain HTTP

## Installation

In your Larasan checkout:

```bash
composer require hrnicek/larasan-mcp
```

### 1. Passport

Publish Passport's migrations, run them and create the signing keys:

```bash
php artisan vendor:publish --tag=passport-migrations
```

```bash
php artisan migrate
```

```bash
php artisan passport:keys
```

In production, provide the keys through `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` or deploy the files in `storage/`; never commit them.

### 2. The user model

`App\Models\User` must be an OAuth subject:

```php
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable, PasskeyUser
{
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, Searchable, TwoFactorAuthenticatable;
```

That is all the application has to change. The package registers the rest:

- an `api` guard with the `passport` driver, unless the application defines its own;
- a consent screen that names the site the authorization code is sent back to;
- the OAuth discovery routes (`/.well-known/oauth-protected-resource`, `/.well-known/oauth-authorization-server`) and dynamic client registration (`POST /oauth/register`), throttled per IP and limited to an allowlist of callback sites;
- the MCP endpoint `POST /mcp/larasan` behind `auth:api`, a required `mcp:use` token scope, and a per-user rate limit.

Check the routes:

```bash
php artisan route:list --path=mcp
```

## Connecting a client

The server URL is `https://<your-larasan>/mcp/larasan`. Each client discovers the OAuth endpoints itself, registers, and sends you to Larasan's sign-in and consent screen. Approve, and the client works as you.

**Claude (claude.ai, Claude Desktop, mobile)** — Settings → Connectors → *Add custom connector*, paste the URL, then *Connect*.

**Claude Code** — install the plugin from this repository. It adds the server and a skill that teaches Claude the tools:

```bash
claude plugin marketplace add hrnicek/larasan-mcp
```

```bash
claude plugin install larasan@larasan
```

Enter your Larasan URL when asked, then run `/mcp`, pick *larasan* and choose *Authenticate*. See [claude-plugin/README.md](claude-plugin/README.md). Without the plugin, add the server alone:

```bash
claude mcp add --transport http larasan https://your-larasan.example/mcp/larasan
```

**ChatGPT** — Settings → Apps & Connectors → enable developer mode under *Advanced*, then *Create* a connector with the URL and OAuth authentication.

**Cursor, VS Code and other clients** — add a remote (streamable HTTP) MCP server with the URL; they follow the same OAuth flow.

To inspect the server by hand:

```bash
php artisan mcp:inspector mcp/larasan
```

## Configuration

Optional. Publish the file to change it:

```bash
php artisan vendor:publish --tag=larasan-mcp-config
```

| Key | Env | Default | |
| --- | --- | --- | --- |
| `http.path` | `LARASAN_MCP_PATH` | `mcp/larasan` | Endpoint path |
| `http.middleware` | | `auth:api`, `mcp:use` scope check, `throttle:larasan-mcp` | Endpoint middleware |
| `oauth.authorization_view` | | `larasan-mcp::authorize` | Consent screen; `null` keeps one the app registered |
| `oauth.redirect_domains` | `LARASAN_MCP_REDIRECT_DOMAINS` | claude.ai, claude.com, chatgpt.com, chat.openai.com, localhost, 127.0.0.1 | Comma-separated sites a self-registered client may call back to |
| `oauth.custom_schemes` | `LARASAN_MCP_CUSTOM_SCHEMES` | `cursor,vscode` | URL schemes desktop editors call back on |
| `oauth.registrations_per_minute` | `LARASAN_MCP_OAUTH_REGISTRATIONS_PER_MINUTE` | `30` | Per IP, discovery and registration routes |
| `requests_per_minute` | `LARASAN_MCP_REQUESTS_PER_MINUTE` | `120` | Per user |
| `page_size` | `LARASAN_MCP_PAGE_SIZE` | `50` | Rows per page in list tools |
| `download_max_kilobytes` | `LARASAN_MCP_DOWNLOAD_MAX_KILOBYTES` | `5120` | Largest file returned inline |

Writes that can be abused keep the web app's per-account budgets: invitations and new workspaces 10/min, new projects 20/min, comments (new and edited) 30/min, uploads 20/min, task moves 60/min.

The redirect allowlist applies while `config/mcp.php` is unpublished (laravel/mcp's default there is `*`, any site). If you publish that file, set `redirect_domains` in it yourself.

## Security notes

- Client registration is public by design (MCP clients register themselves), so the callback allowlist is what stops a stranger registering "Claude" with their own callback and phishing a token. Add the site of any other MCP client you use to `LARASAN_MCP_REDIRECT_DOMAINS`.
- The consent screen shows the site the code goes to; the client's name is chosen by whoever registered it and proves nothing.
- Tokens must carry the `mcp:use` scope, so a Passport token issued for anything else is refused. They identify a Larasan account; permissions come from that account's workspace role and project access, checked on every call.
- Revoking access: delete the client's tokens from `oauth_access_tokens` / `oauth_refresh_tokens`, or the client from `oauth_clients`.
- Uploads are decoded into a temporary file and validated against `config('attachments')` by content, exactly like browser uploads. SVG is never accepted or returned inline.
- `download_attachment` is authorised per task, like Larasan's own download route; `download_url` still requires a signed-in browser session.

## Testing

The tests run inside a Larasan checkout, against its PostgreSQL test database and factories:

```bash
vendor/bin/pest --test-directory=vendor/hrnicek/larasan-mcp/tests vendor/hrnicek/larasan-mcp/tests
```

They cover every tool, workspace isolation, private projects, guest limits, throttling, and the full OAuth 2.1 flow from client registration through PKCE to an authenticated tool call. CI runs them against `hrnicek/larasan` on every push.

## License

MIT. See [LICENSE](LICENSE).
