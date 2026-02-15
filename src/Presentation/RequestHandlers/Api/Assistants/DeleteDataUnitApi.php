<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Assistants;

use Assistant\Application\Commands\DeleteDataUnitCommand;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Dataset\Domain\Entities\AbstractDataUnitEntity;
use Presentation\AccessControls\AssistantAccessControl;
use Presentation\AccessControls\Permission;
use Presentation\Exceptions\NotFoundException;
use Presentation\Response\EmptyResponse;
use Shared\Infrastructure\CommandBus\Dispatcher;
use User\Domain\Entities\UserEntity;

#[Route(path: '/[uuid:aid]/dataset/[uuid:id]', method: RequestMethod::DELETE)]
class DeleteDataUnitApi extends AssistantApi implements
    RequestHandlerInterface
{
    public function __construct(
        private AssistantAccessControl $ac,
        private Dispatcher $dispatcher
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request);

        $cmd = new DeleteDataUnitCommand(
            $request->getAttribute('aid'),
            $request->getAttribute('id')
        );

        try {
            /** @var AbstractDataUnitEntity */
            $this->dispatcher->dispatch($cmd);
        } catch (AssistantNotFoundException $th) {
            throw new NotFoundException(
                param: 'id',
                previous: $th
            );
        }

        return new EmptyResponse();
    }

    private function validateRequest(ServerRequestInterface $req): void
    {
        /** @var UserEntity */
        $user = $req->getAttribute(UserEntity::class);

        $this->ac->denyUnlessGranted(
            Permission::ASSISTANT_EDIT,
            $user,
            $req->getAttribute('aid')
        );
    }
}
