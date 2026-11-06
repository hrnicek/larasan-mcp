<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools\Workspaces;

use App\Models\User;
use Larasan\Mcp\Tools\LarasanTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_workspaces')]
#[Description('List the workspaces you are an active member of, with your role in each.')]
#[IsReadOnly]
final class ListWorkspaces extends LarasanTool
{
    protected function run(Request $request, User $actor): ResponseFactory
    {
        return $this->reply(['workspaces' => WhoAmI::workspacesOf($actor)]);
    }
}
