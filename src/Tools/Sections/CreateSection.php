<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Sections;

use App\Domain\Section\Actions\CreateSection as CreateSectionAction;
use App\Domain\Section\Data\CreateSectionData;
use App\Domain\Shared\Rules\IsAccentColor;
use App\Domain\Shared\ValueObjects\AccentColor;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_section')]
#[Description('Add a section (a board column or list group) to the end of a project.')]
final class CreateSection extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'color' => $schema->string()->description('Palette colour name or #rrggbb.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $project = Find::project($actor, $request->string('project_id')->toString());

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', new IsAccentColor],
        ]);

        $data = isset($input['color'])
            ? new CreateSectionData($input['name'], new AccentColor($input['color']))
            : new CreateSectionData($input['name']);

        $section = app(CreateSectionAction::class)->handle($project, $actor, $data);

        return $this->reply(['section' => Present::section($section)]);
    }
}
