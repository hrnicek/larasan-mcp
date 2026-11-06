<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Attachments;

use App\Domain\File\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_attachments')]
#[Description('List the files attached to a task.')]
#[IsReadOnly]
final class ListAttachments extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        return $this->reply([
            'task_id' => $task->id,
            'attachments' => $task->attachments()
                ->with('file')
                ->orderBy('position')
                ->get()
                ->map(fn (Attachment $attachment): array => Present::attachment($attachment))
                ->all(),
        ]);
    }
}
