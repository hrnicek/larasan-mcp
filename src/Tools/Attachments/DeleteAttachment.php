<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Attachments;

use App\Domain\File\Actions\DetachFile;
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

#[Name('delete_attachment')]
#[Description('Remove a file from a task.')]
#[IsDestructive]
final class DeleteAttachment extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'attachment_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $attachment = Find::attachment($actor, $request->string('attachment_id')->toString());

        if ($actor->cannot('delete', $attachment)) {
            throw ToolRefusal::forbidden('remove this file');
        }

        app(DetachFile::class)->handle($attachment, $actor);

        return $this->reply(['deleted' => true, 'attachment_id' => $attachment->id]);
    }
}
