<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Projects;

use App\Domain\Project\Actions\UpdateProject as UpdateProjectAction;
use App\Domain\Project\Data\UpdateProjectData;
use App\Domain\Shared\Enums\ProjectDefaultView;
use App\Domain\Shared\Enums\ProjectIcon;
use App\Domain\Shared\Enums\ProjectVisibility;
use App\Domain\Shared\ValueObjects\AccentColor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Larasan\Mcp\Support\Find;
use Larasan\Mcp\Support\Present;
use Larasan\Mcp\Support\ProjectFields;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_project')]
#[Description('Change a project\'s name, description, colour, icon, visibility, default view or dates. Omitted fields stay as they are; pass null to clear description, colour, icon or a date.')]
#[IsIdempotent]
final class UpdateProject extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->required(),
            'name' => $schema->string(),
            ...ProjectFields::schema($schema),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $project = Find::project($actor, $request->string('project_id')->toString());

        if ($actor->cannot('update', $project)) {
            throw ToolRefusal::forbidden('change this project');
        }

        $input = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            ...ProjectFields::rules($project->workspace_id, $project->id),
        ]);

        // The Action clears a nullable column it receives as null, so absent fields carry the current value.
        $given = fn (string $key): bool => array_key_exists($key, $input);
        $date = fn (string $key, ?CarbonImmutable $current): ?CarbonImmutable => $given($key)
            ? ($input[$key] === null ? null : CarbonImmutable::parse($input[$key]))
            : $current;

        $project = app(UpdateProjectAction::class)->handle($project, $actor, new UpdateProjectData(
            name: $input['name'] ?? $project->name,
            slug: $input['slug'] ?? null,
            description: $given('description') ? $input['description'] : $project->description,
            color: $given('color') ? AccentColor::tryFrom($input['color']) : $project->color,
            icon: $given('icon') ? ProjectIcon::tryFrom((string) $input['icon']) : $project->icon,
            defaultView: ProjectDefaultView::tryFrom((string) ($input['default_view'] ?? '')),
            visibility: ProjectVisibility::tryFrom((string) ($input['visibility'] ?? '')),
            startDate: $date('start_date', $project->start_date),
            dueDate: $date('due_date', $project->due_date),
        ));

        return $this->reply(['project' => Present::project($project)]);
    }
}
