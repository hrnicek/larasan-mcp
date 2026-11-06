<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Comments;

use App\Domain\Comment\Actions\UpdateComment as UpdateCommentAction;
use App\Domain\Comment\Data\UpdateCommentData;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_comment')]
#[Description('Edit the text of one of your own comments.')]
#[IsIdempotent]
final class UpdateComment extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->string()->required(),
            'body' => $schema->string()->description('Plain text, up to 5000 characters.')->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $comment = Find::comment($actor, $request->string('comment_id')->toString());

        if ($actor->cannot('update', $comment)) {
            throw ToolRefusal::forbidden('edit this comment');
        }

        $input = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        // An edit re-notifies everyone still mentioned, so it shares the comment budget.
        Throttle::hit('comments', $actor, 30);

        $comment = app(UpdateCommentAction::class)->handle($comment, $actor, new UpdateCommentData($input['body']));

        return $this->reply(['comment' => Present::comment($comment->load('author'))]);
    }
}
