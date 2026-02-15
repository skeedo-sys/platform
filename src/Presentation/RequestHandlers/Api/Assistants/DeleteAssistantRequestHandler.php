<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Assistants;

use Assistant\Application\Commands\DeleteAssistantCommand;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Presentation\Exceptions\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Presentation\Response\EmptyResponse;
use Presentation\AccessControls\AssistantAccessControl;
use User\Domain\Entities\UserEntity;
use Presentation\AccessControls\Permission;

#[Route(path: '/[uuid:id]', method: RequestMethod::DELETE)]
class DeleteAssistantRequestHandler extends AssistantApi
implements RequestHandlerInterface
{
    public function __construct(
        private AssistantAccessControl $ac,
        private Dispatcher $dispatcher
    ) {}


    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request);

        $id = $request->getAttribute('id');

        try {
            $cmd = new DeleteAssistantCommand($id);
            $this->dispatcher->dispatch($cmd);
        } catch (AssistantNotFoundException $th) {
            throw new NotFoundException(
                param: 'id',
                previous: $th
            );
        }

        return new EmptyResponse;
    }

    private function validateRequest(ServerRequestInterface $req): void
    {
        /** @var UserEntity */
        $user = $req->getAttribute(UserEntity::class);

        $this->ac->denyUnlessGranted(
            Permission::ASSISTANT_DELETE,
            $user,
            $req->getAttribute('id')
        );
    }
}
