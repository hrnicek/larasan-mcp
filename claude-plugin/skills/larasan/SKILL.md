---
name: larasan
description: Work with Larasan, the project manager, through its MCP tools — find, create, update, move, assign and complete tasks, comment, attach files, and manage projects, sections and workspaces. Use when the user mentions Larasan, their tasks, board, backlog or project plan, asks what is on their plate, or wants coding work linked to a Larasan task.
---

# Larasan

Larasan tools come from the `larasan` MCP server. They act as the person who signed in, with exactly their
permissions: a refusal means that person may not do it, not that the tool is broken.

## First call

Run `whoami` before anything else in a session. It returns the person, their workspaces and the current
workspace. Tools with an optional `workspace_id` use the current workspace when it is omitted.

If the Larasan tools are missing or every call fails with an authentication error, tell the user to run `/mcp`,
pick **larasan** and choose **Authenticate**. Sign-in happens in their browser on their own Larasan.

## Model

workspace → projects → sections (board columns) → tasks. A task can sit on several projects; a task with no
project lives only in the workspace's My Tasks. Ids are UUIDs, except people, who have integer ids from
`list_members`.

## Look before you write

- Find work with `search_tasks` (`assignee: "me"`, `due_before: <today>` for overdue) or `list_tasks` for one
  project or section. Read a task with `get_task` before changing it.
- Resolve names to ids yourself: projects with `list_projects` / `get_project` (which lists its sections),
  people with `list_members`, tags with `list_tags`. Ask the user only when a name matches more than one.
- Results are paginated; follow the cursor when the user asked for "all".

## Writing

- Task descriptions take Markdown. Comments are plain text; mention someone with `@[Name](user:id)`, which
  notifies them.
- Moving a card between columns is `move_task` with the target `section_id`. `move_task` only adds a task to a
  project; `remove_task_from_project` takes it off.
- Dates are `YYYY-MM-DD`. Turn "Friday" or "next week" into a date from today's date and say which date you used.
- Attachments travel as base64 within the server's size and type limits; large downloads return metadata only.

## Destructive actions

Ask the user before `delete_task`, `delete_section`, `delete_comment`, `delete_attachment` and
`archive_project`, naming the item. Never delete to "clean up" on your own initiative.

## Linking coding work

When the user ties work in this repository to a task:

1. Find the task (`search_tasks` by title, or the id they give) and read it with `get_task`.
2. Use its description and comments as the requirements for the change.
3. When the work is done and verified, offer to add a comment summarising what changed (branch, commit or PR
   link, how it was tested) and to move the task to the project's done section or `complete_task`. Do both only
   after the user agrees.
