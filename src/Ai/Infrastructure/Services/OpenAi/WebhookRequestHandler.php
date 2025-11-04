<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Easy\Container\Attributes\Inject;
use Easy\Http\Message\RequestMethod;
use Easy\Http\Message\StatusCode;
use Easy\Router\Attributes\Middleware;
use Easy\Router\Attributes\Route;
use Exception;
use Presentation\Exceptions\HttpException;
use Presentation\Middlewares\ExceptionMiddleware;
use Presentation\Response\EmptyResponse;
use Presentation\Validation\StandardWebhook;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;

#[Middleware(ExceptionMiddleware::class)]
#[Route(path: '/webhooks/openai', method: RequestMethod::POST)]
class WebhookRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private ContainerInterface $container,

        #[Inject('option.openai.webhook_secret')]
        private ?string $secret = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $webhook = new StandardWebhook($this->secret);
            $payload = $webhook->verify($request);
        } catch (Exception $e) {
            throw new HttpException(
                $e->getMessage(),
                StatusCode::BAD_REQUEST
            );
        }

        if (!$payload) {
            throw new HttpException(
                'Invalid request',
                StatusCode::BAD_REQUEST
            );
        }

        $type = $payload->type;
        if (in_array($type, ['video.completed', 'video.failed'])) {
            $processor = $this->container->get(VideoWebhookProcessor::class);
            $processor($payload);
        }

        return new EmptyResponse();
    }
}
