<?php

declare(strict_types=1);

namespace Larasan\Mcp\Tools;

use App\Domain\Shared\Exceptions\DomainRefusal;
use App\Models\User;
use DomainException;
use Larasan\Mcp\Support\ToolRefusal;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Every tool acts as the signed-in account and goes through Larasan's own Actions and policies.
 * Refusals come back as tool errors the model can read and recover from.
 */
abstract class LarasanTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return Response::error('Not signed in to Larasan.');
        }

        try {
            return $this->run($request, $actor);
        } catch (ToolRefusal $refusal) {
            return Response::error($refusal->getMessage());
        } catch (DomainException $exception) {
            if (! $exception instanceof DomainRefusal) {
                throw $exception;
            }

            return Response::error($exception->getMessage());
        }
    }

    abstract protected function run(Request $request, User $actor): Response|ResponseFactory;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function reply(array $payload): ResponseFactory
    {
        return Response::structured($payload);
    }
}
