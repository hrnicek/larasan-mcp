<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Projects;

use App\Domain\Project\Actions\CreateProject as CreateProjectAction;
use App\Domain\Project\Data\CreateProjectData;
use App\Domain\Shared\Enums\Capability;
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
use Larasan\Mcp\Support\Throttle;
use Larasan\Mcp\Support\ToolRefusal;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('create_project')]
#[Description('Create a project in a workspace. You become its owner.')]
final class CreateProject extends LarasanTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('Workspace id. Defaults to your current workspace.'),
            'name' => $schema->string()->required(),
            ...ProjectFields::schema($schema),
        ];
    }

    protected function run(Request $request, User $actor): ResponseFactory
    {
        $workspace = Find::workspace($actor, $request->string('workspace_id')->value() ?: null);

        if ($actor->cannot(Capability::ProjectCreate->value, $workspace)) {
            throw ToolRefusal::forbidden('create projects in this workspace');
        }

        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            ...ProjectFields::rules($workspace->id),
        ]);

        Throttle::hit('projects', $actor, 20);

        $project = app(CreateProjectAction::class)->handle($workspace, $actor, new CreateProjectData(
            name: $input['name'],
            slug: $input['slug'] ?? null,
            description: $input['description'] ?? null,
            color: AccentColor::tryFrom($input['color'] ?? null),
            icon: ProjectIcon::tryFrom($input['icon'] ?? ''),
            defaultView: ProjectDefaultView::tryFrom($input['default_view'] ?? '') ?? ProjectDefaultView::List,
            visibility: ProjectVisibility::tryFrom($input['visibility'] ?? '') ?? ProjectVisibility::Workspace,
            startDate: isset($input['start_date']) ? CarbonImmutable::parse($input['start_date']) : null,
            dueDate: isset($input['due_date']) ? CarbonImmutable::parse($input['due_date']) : null,
        ));

        return $this->reply(['project' => Present::project($project)]);
    }
}
