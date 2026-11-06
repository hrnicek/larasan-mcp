<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Comments;

use App\Domain\Comment\Actions\DeleteComment as DeleteCommentAction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_comment')]
#[Description('Remove a comment.')]
#[IsDestructive]
final class DeleteComment extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $comment = Find::comment($actor, $request->string('comment_id')->toString());

        if ($actor->cannot('delete', $comment)) {
            throw ToolRefusal::forbidden('remove this comment');
        }

        app(DeleteCommentAction::class)->handle($comment, $actor);

        return $this->reply(['deleted' => true, 'comment_id' => $comment->id]);
    }
}
