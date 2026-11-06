<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Sections;

use App\Domain\Section\Actions\RenameSection;
use App\Domain\Section\Data\UpdateSectionData;
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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_section')]
#[Description('Rename a section or change its colour.')]
#[IsIdempotent]
final class UpdateSection extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'color' => $schema->string()->description('Palette colour name or #rrggbb. Omit to keep the current colour.'),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $section = Find::section($actor, $request->string('section_id')->toString());

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', new IsAccentColor],
        ]);

        $section = app(RenameSection::class)->handle($section, $actor, new UpdateSectionData(
            name: $input['name'],
            color: isset($input['color']) ? new AccentColor($input['color']) : $section->color,
        ));

        return $this->reply(['section' => Present::section($section)]);
    }
}
