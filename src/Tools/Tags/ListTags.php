<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tags;

use App\Domain\Tag\Models\Tag;
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

#[Name('list_tags')]
#[Description('List the tags of a workspace.')]
#[IsReadOnly]
final class ListTags extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);

        return $this->reply([
            'workspace_id' => $workspace->id,
            'tags' => $workspace->tags()->orderBy('name')->get()->map(fn (Tag $tag): array => Present::tag($tag))->all(),
        ]);
    }
}
