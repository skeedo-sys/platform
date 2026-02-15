<?php

declare(strict_types=1);

namespace Presentation\RequestHandlers\Api\Assistants;

use Assistant\Application\Commands\CreateAssistantCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\ValueObjects\Status;
use Easy\Container\Attributes\Inject;
use Easy\Http\Message\RequestMethod;
use Easy\Http\Message\StatusCode;
use Easy\Router\Attributes\Route;
use League\Flysystem\Visibility;
use Presentation\Exceptions\HttpException;
use Presentation\Exceptions\NotFoundException;
use Presentation\Resources\Api\AssistantResource;
use Presentation\Response\JsonResponse;
use Presentation\Validation\Validator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Shared\Infrastructure\FileSystem\CdnInterface;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Route(path: '/', method: RequestMethod::POST)]
class CreateAssistantRequestHandler extends AssistantApi implements
    RequestHandlerInterface
{
    public function __construct(
        private Validator $validator,
        private Dispatcher $dispatcher,
        private CdnInterface $cdn,

        #[Inject('option.features.chat.is_enabled')]
        private bool $isEnabled = false,

        #[Inject('option.features.chat.custom_assistants.is_enabled')]
        private bool $isCustomAssistantsEnabled = false,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->isEnabled || !$this->isCustomAssistantsEnabled) {
            throw new NotFoundException();
        }

        /** @var UserEntity */
        $user = $request->getAttribute(UserEntity::class);
        /** @var WorkspaceEntity */
        $ws = $request->getAttribute(WorkspaceEntity::class);

        if ($ws->isAssistantCapExceeded()) {
            throw new HttpException(
                'Custom assistant limit reached for the workspace.',
                StatusCode::FORBIDDEN
            );
        }

        $this->validateRequest($request);
        $payload = (object) $request->getParsedBody();

        $cmd = new CreateAssistantCommand(
            name: $payload->name
        );

        $cmd->status = Status::ACTIVE;
        $cmd->workspace = $ws;
        $cmd->user = $user;

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

        /** @var AssistantEntity */
        $assistant = $this->dispatcher->dispatch($cmd);

        return new JsonResponse(
            new AssistantResource($assistant),
            StatusCode::CREATED
        );
    }

    private function validateRequest(ServerRequestInterface $req): void
    {
        $this->validator->validateRequest($req, [
            'name' => 'required|string',
            'expertise' => 'string',
            'description' => 'string',
            'instructions' => 'string',
            'visibility' => 'integer|in:0,1,2',
            'model' => 'string',
        ]);
    }

    private function getAvatarUrl(string $avatar): ?string
    {
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
