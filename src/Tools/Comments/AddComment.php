<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Comments;

use App\Domain\Comment\Actions\CreateComment;
use App\Domain\Comment\Data\CreateCommentData;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_comment')]
#[Description('Comment on a task as yourself. Plain text. Mention a member with @[Name](user:id) (see list_members); they are notified.')]
final class AddComment extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'body' => $schema->string()->description('Plain text, up to 5000 characters.')->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());

        if ($actor->cannot('comment', $task)) {
            throw ToolRefusal::forbidden('comment on this task');
        }

        $input = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        Throttle::hit('comments', $actor, 30);

        $comment = app(CreateComment::class)->handle($task, $actor, new CreateCommentData($input['body']));

        return $this->reply(['comment' => Present::comment($comment->load('author'))]);
    }
}
