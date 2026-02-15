<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Assistants;

use Ai\Infrastructure\Exceptions\UnreadableDocumentException;
use Assistant\Application\Commands\CreateDataUnitCommand;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use Override;
use Presentation\Validation\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Dataset\Domain\Entities\AbstractDataUnitEntity;
use Dataset\Domain\Entities\FileUnitEntity;
use Dataset\Domain\Entities\LinkUnitEntity;
use Easy\Http\Message\StatusCode;
use Presentation\AccessControls\AssistantAccessControl;
use Presentation\AccessControls\Permission;
use Presentation\Exceptions\HttpException;
use Presentation\Resources\Api\FileUnitResource;
use Presentation\Resources\Api\LinkUnitResource;
use Presentation\Response\JsonResponse;
use Psr\Http\Message\UploadedFileInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Route(path: '/[uuid:id]/dataset', method: RequestMethod::POST)]
class CreateDataUnitApi extends AssistantApi implements
    RequestHandlerInterface
{
    public function __construct(
        private AssistantAccessControl $ac,
        private Validator $validator,
        private Dispatcher $dispatcher
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request);

        /** @var WorkspaceEntity */
        $workspace = $request->getAttribute(WorkspaceEntity::class);

        /** @var object{url?:string} */
        $payload = (object)$request->getParsedBody();

        $id = $request->getAttribute('id');
        $cmd = new CreateDataUnitCommand($id);
        $cmd->workspace = $workspace;

        /** @var UploadedFileInterface */
        $file = $request->getUploadedFiles()['file'] ?? null;

        if ($file) {
            $cmd->file = $file;
        }

        if (property_exists($payload, 'url')) {
            $cmd->setUrl($payload->url);
        }

        try {
            /** @var AbstractDataUnitEntity */
            $res = $this->dispatcher->dispatch($cmd);
        } catch (UnreadableDocumentException $th) {
            throw new HttpException(
                message: $th->getMessage(),
                statusCode: StatusCode::UNPROCESSABLE_ENTITY,
                previous: $th
            );
        }

        match (true) {
            $res instanceof FileUnitEntity => $resource = new FileUnitResource($res),
            $res instanceof LinkUnitEntity => $resource = new LinkUnitResource($res),
            default => $resource = [],
        };

        return new JsonResponse($resource);
    }

    private function validateRequest(ServerRequestInterface $req): void
    {
        /** @var UserEntity */
        $user = $req->getAttribute(UserEntity::class);

        $this->ac->denyUnlessGranted(
            Permission::ASSISTANT_EDIT,
            $user,
            $req->getAttribute('id')
        );

        $this->validator->validateRequest($req, [
            'file' => 'sometimes|uploaded_file|mimes:pdf,csv,json,txt,xml,doc,docx,odt',
            'url' => 'sometimes|url',
        ]);
    }
}
