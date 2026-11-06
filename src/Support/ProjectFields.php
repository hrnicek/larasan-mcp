<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Shared\Enums\ProjectColor;
use App\Domain\Shared\Enums\ProjectDefaultView;
use App\Domain\Shared\Enums\ProjectIcon;
use App\Domain\Shared\Enums\ProjectVisibility;
use App\Domain\Shared\Rules\IsAccentColor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * The optional project fields `create_project` and `update_project` share, mirroring Larasan's project forms.
 */
final readonly class ProjectFields
{
    /**
     * @return array<string, mixed>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->description('URL slug, unique in the workspace (lowercase, hyphens).'),
            'description' => $schema->string()->description('Plain text, up to 2000 characters.'),
            'color' => $schema->string()->description('A palette colour ('.implode(', ', array_column(ProjectColor::cases(), 'value')).') or a #rrggbb hex.'),
            'icon' => $schema->string()->enum(array_column(ProjectIcon::cases(), 'value')),
            'visibility' => $schema->string()->enum(array_column(ProjectVisibility::cases(), 'value'))
                ->description('"workspace": every member can see it. "private": only people added to the project.'),
            'default_view' => $schema->string()->enum(array_column(ProjectDefaultView::cases(), 'value')),
            'start_date' => $schema->string()->format('date')->description('YYYY-MM-DD.'),
            'due_date' => $schema->string()->format('date')->description('YYYY-MM-DD, not before start_date.'),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(string $workspaceId, ?string $ignoreProjectId = null): array
    {
        return [
            // Checks the table, not the model, so soft-deleted projects still hold their slugs.
            'slug' => [
                'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')
                    ->where(fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
                    ->ignore($ignoreProjectId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', new IsAccentColor],
            'icon' => ['nullable', Rule::enum(ProjectIcon::class)],
            'default_view' => ['nullable', Rule::enum(ProjectDefaultView::class)],
            'visibility' => ['nullable', Rule::enum(ProjectVisibility::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
