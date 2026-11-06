<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Shared\Enums\TaskPriority;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The editable task fields shared by `create_task` and `update_task`.
 */
final readonly class TaskFields
{
    /**
     * @return array<string, mixed>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->description('Markdown (headings, bold, italics, lists, links, code). Stored as rich text.'),
            'priority' => $schema->string()->enum(array_column(TaskPriority::cases(), 'value')),
            'due_at' => $schema->string()->description('ISO 8601 date or date-time, e.g. 2026-11-20 or 2026-11-20T14:00:00+01:00.'),
            'parent_id' => $schema->string()->description('Make this a subtask of the task with this id.'),
        ];
    }
}
