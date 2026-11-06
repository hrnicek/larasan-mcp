<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Tags;

use App\Domain\Shared\Rules\IsAccentColor;
use App\Domain\Shared\ValueObjects\AccentColor;
use App\Domain\Tag\Actions\AttachTagToTask;
use App\Domain\Tag\Actions\CreateTag;
use App\Domain\Tag\Models\Tag;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('tag_task')]
#[Description('Add a tag to a task, by tag_id or by name. A name that is not a tag yet creates it, if you may manage tags.')]
#[IsIdempotent]
final class TagTask extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->required(),
            'tag_id' => $schema->string(),
            'name' => $schema->string()->description('Tag name, up to 40 characters. Used when tag_id is omitted.'),
            'color' => $schema->string()->description('Colour for a newly created tag.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $task = Find::task($actor, $request->string('task_id')->toString());
        $input = $request->validate([
            'tag_id' => ['nullable', 'string', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:40', 'required_without:tag_id'],
            'color' => ['nullable', new IsAccentColor],
        ]);

        $tag = isset($input['tag_id'])
            ? Find::tag($actor, $input['tag_id'])
            : $this->tagNamed($task->workspace, $actor, trim((string) $input['name']), $input['color'] ?? null);

        if ($tag->workspace_id !== $task->workspace_id) {
            throw ToolRefusal::notFound('tag');
        }

        app(AttachTagToTask::class)->handle($task, $tag, $actor);

        return $this->reply(['task_id' => $task->id, 'tag' => Present::tag($tag)]);
    }

    private function tagNamed(Workspace $workspace, User $actor, string $name, ?string $color): Tag
    {
        return $workspace->tags()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
            ?? app(CreateTag::class)->handle($workspace, $actor, $name, AccentColor::tryFrom($color));
    }
}
