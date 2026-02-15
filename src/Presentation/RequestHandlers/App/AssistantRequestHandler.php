<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\App;

use Assistant\Application\Commands\ReadAssistantCommand;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Presentation\AccessControls\AssistantAccessControl;
use Presentation\AccessControls\Permission;
use Presentation\Resources\Api\AssistantResource;
use Presentation\Response\RedirectResponse;
use Presentation\Response\ViewResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use User\Domain\Entities\UserEntity;
use User\Domain\Exceptions\UserNotFoundException;

#[Route(path: '/assistants/[uuid:id]', method: RequestMethod::GET)]
#[Route(path: '/assistants/new', method: RequestMethod::GET)]
class AssistantRequestHandler extends AppView implements
    RequestHandlerInterface
{
    public function __construct(
        private AssistantAccessControl $ac,
        private Dispatcher $dispatcher
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        if ($id && !$this->isGranted($request)) {
            return new RedirectResponse('/app/assistants');
        }


        $data = [];

        if ($id) {
            $cmd = new ReadAssistantCommand($id);

            try {
                $assistant = $this->dispatcher->dispatch($cmd);
            } catch (UserNotFoundException $th) {
                return new RedirectResponse('/app/assistants');
            }

            $extend = ['dataset', 'instructions'];
            $data['assistant'] = new AssistantResource(
                $assistant,
                extend: $extend
            );
        }

        return new ViewResponse(
            '/templates/app/assistant.twig',
            $data
        );
    }

    private function isGranted(ServerRequestInterface $request): bool
    {
        /** @var UserEntity */
        $user = $request->getAttribute(UserEntity::class);

        return $this->ac->isGranted(
            Permission::ASSISTANT_EDIT,
            $user,
            $request->getAttribute('id')
        );
    }
}
