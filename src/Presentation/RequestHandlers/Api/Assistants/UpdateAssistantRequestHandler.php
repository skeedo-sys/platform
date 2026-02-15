<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Assistants;

use Assistant\Application\Commands\UpdateAssistantCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Easy\Http\Message\RequestMethod;
use Easy\Router\Attributes\Route;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\FilesystemException;
use League\Flysystem\Visibility;
use Presentation\AccessControls\AssistantAccessControl;
use Presentation\AccessControls\Permission;
use Presentation\Exceptions\NotFoundException;
use Presentation\Resources\Api\AssistantResource;
use Presentation\Response\JsonResponse;
use Presentation\Validation\ValidationException;
use Presentation\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Shared\Infrastructure\CommandBus\Exception\NoHandlerFoundException;
use Shared\Infrastructure\FileSystem\CdnInterface;
use User\Domain\Entities\UserEntity;

#[Route(path: '/[uuid:id]', method: RequestMethod::PATCH)]
#[Route(path: '/[uuid:id]', method: RequestMethod::POST)]
class UpdateAssistantRequestHandler extends AssistantApi implements
    RequestHandlerInterface
{
    public function __construct(
        private AssistantAccessControl $ac,
        private Validator $validator,
        private Dispatcher $dispatcher,
        private CdnInterface $cdn
    ) {}

    /**
     * @throws ValidationException
     * @throws UnableToWriteFile
     * @throws FilesystemException
     * @throws NotFoundException
     * @throws NoHandlerFoundException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->validateRequest($request);
        $payload = (object) $request->getParsedBody();

        $cmd = new UpdateAssistantCommand($request->getAttribute('id'));

        if (property_exists($payload, 'name')) {
            $cmd->setName($payload->name);
        }

        if (property_exists($payload, 'expertise')) {
            $cmd->setExpertise($payload->expertise ?: null);
        }

        if (property_exists($payload, 'description')) {
            $cmd->setDescription($payload->description ?: null);
        }

        if (property_exists($payload, 'instructions')) {
            $cmd->setInstructions($payload->instructions ?: null);
        }

        if (property_exists($payload, 'avatar')) {
            $url = $this->getAvatarUrl($payload->avatar);
            $cmd->setAvatar($url);
        }

        if (property_exists($payload, 'model')) {
            $cmd->setModel($payload->model ?: null);
        }

        if (property_exists($payload, 'visibility')) {
            $cmd->setVisibility((int) $payload->visibility);
        }

        try {
            /** @var AssistantEntity */
            $assistant = $this->dispatcher->dispatch($cmd);
        } catch (AssistantNotFoundException $th) {
            throw new NotFoundException(
                param: 'id',
                previous: $th
            );
        }

        return new JsonResponse(new AssistantResource($assistant, extend: ['dataset']));
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
            'name' => 'string',
            'expertise' => 'string',
            'description' => 'string',
            'instructions' => 'string',
            'visibility' => 'integer|in:0,1,2',
            'model' => 'string',
        ]);
    }

    private function getAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) {
            return null;
        }

        // check if its a valid url
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        // Decode the Base64 string
        $fileData = base64_decode($avatar);

        if (!$fileData) {
            return null;
        }

        // Determine the file type from the binary data
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $fileData);
        finfo_close($finfo);

        $mimeTypeToExtension = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/webp' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        ];

        if (!array_key_exists($mimeType, $mimeTypeToExtension)) {
            return null;
        }

        $ext = $mimeTypeToExtension[$mimeType];

        $name = Uuid::uuid4()->toString() . '.' . $ext;
        $this->cdn->write("/" . $name, $fileData, [
            // Always make it public even though the pre-signed secure URLs option is enabled.
            'visibility' => Visibility::PUBLIC
        ]);

        $url = $this->cdn->getUrl($name);

        // Remove query string from URL if present
        return strstr($url, '?', true) ?: $url;
    }
}
