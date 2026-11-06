# Changelog

## v0.1.0 — 2026-11-06

First release.

- MCP server for Larasan on laravel/mcp, served over streamable HTTP at `/mcp/larasan`.
- Sign-in through OAuth 2.1 with Laravel Passport: discovery metadata, dynamic client registration, PKCE.
- 35 tools across workspaces, members and invitations, projects, sections, tasks, tags, comments and attachments.
- Every tool acts through Larasan's own Actions and policies; foreign and malformed ids share one "not visible" answer.
- Markdown task descriptions stored as Larasan rich text; base64 attachments validated like browser uploads.
- Client registration limited to an allowlist of callback sites; the consent screen names the site the code goes to.
- The endpoint requires the `mcp:use` token scope.
- Per-user rate limits, plus the web app's own budgets for invitations, workspaces, projects, comments, uploads and task moves.
