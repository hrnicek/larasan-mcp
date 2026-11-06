# Larasan plugin for Claude Code

Connects Claude Code to your Larasan through the [Larasan MCP server](../README.md) and adds a skill that teaches
Claude how to use its tools: look up your tasks, plan projects, move cards on a board, comment, attach files, and
tie coding work in a repository to a Larasan task.

Sign-in is OAuth 2.1. There is no API key to copy: Claude Code registers itself with your Larasan, opens the
consent screen in your browser, and from then on works as you, with your permissions.

## Requirements

- A Larasan with `hrnicek/larasan-mcp` installed and reachable over HTTPS.
- Claude Code v2.1.269 or later.

## Install

```bash
claude plugin marketplace add hrnicek/larasan-mcp
```

```bash
claude plugin install larasan@larasan
```

Claude Code asks for **Larasan URL** when the plugin is enabled. Enter the address of your Larasan without a
trailing slash, such as `https://larasan.example.com`. Change it later in `/config`.

Then run `/mcp` in Claude Code, pick **larasan** and choose **Authenticate**. Approve the consent screen in the
browser and return to Claude Code.

## Use

Ask in plain language:

- "What's on my plate in Larasan this week?"
- "Create a *Website relaunch* project with To do / Doing / Done and add a task for the homepage copy, due Friday."
- "Read the Larasan task *Fix invoice rounding* and implement it here."
- "Comment on that task with what we changed and move it to Done."

Claude asks before deleting tasks, sections, comments or attachments, or archiving a project.

## What the plugin contains

| File | Purpose |
| --- | --- |
| `.claude-plugin/plugin.json` | Manifest and the `larasan_url` setting |
| `.mcp.json` | The `larasan` HTTP MCP server at `<larasan_url>/mcp/larasan` |
| `skills/larasan/SKILL.md` | How Claude uses the Larasan tools |

## Develop locally

```bash
claude plugin validate ./claude-plugin
```

```bash
claude --plugin-dir ./claude-plugin
```

OAuth clients refuse plain HTTP outside localhost, so point `larasan_url` at an HTTPS address when you test against
a real server.
