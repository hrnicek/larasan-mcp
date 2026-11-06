<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tags;

use App\Domain\Tag\Actions\DetachTagFromTask;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('untag_task')]
#[Description('Remove a tag from a task. The tag itself stays in the workspace.')]
#[IsIdempotent]
final class UntagTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'tag_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());
        $tag = Find::tag($actor, $request->string('tag_id')->toString());

        app(DetachTagFromTask::class)->handle($task, $tag, $actor);

        return $this->reply(['task_id' => $task->id, 'removed_tag_id' => $tag->id]);
    }
}
