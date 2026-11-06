<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Sections;

use App\Domain\Section\Actions\DeleteSection as DeleteSectionAction;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_section')]
#[Description('Delete a section. Its tasks are kept and move to the project\'s ungrouped tasks.')]
#[IsDestructive]
final class DeleteSection extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $section = Find::section($actor, $request->string('section_id')->toString());

        app(DeleteSectionAction::class)->handle($section, $actor);

        return $this->reply(['deleted' => true, 'section_id' => $section->id]);
    }
}
