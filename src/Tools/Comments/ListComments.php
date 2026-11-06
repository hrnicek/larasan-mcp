<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Comments;

use App\Domain\Comment\Models\Comment;
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

#[Name('list_comments')]
#[Description('List the comments on a task, oldest first. Mentions read as @[Name](user:id). Paginated.')]
#[IsReadOnly]
final class ListComments extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'page' => $schema->integer()->min(1)->default(1),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        $comments = $task->comments()
            ->with('author')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate((int) config('larasan-mcp.page_size'), page: max(1, $request->integer('page', 1)));

        return $this->reply([
            'task_id' => $task->id,
            'comments' => $comments->getCollection()->map(fn (Comment $comment): array => Present::comment($comment))->all(),
            'page' => $comments->currentPage(),
            'last_page' => $comments->lastPage(),
            'total' => $comments->total(),
        ]);
    }
}
