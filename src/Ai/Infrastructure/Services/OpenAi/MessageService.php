<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Ai\Domain\Completion\MessageServiceInterface;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\Entities\MessageEntity;
use Ai\Domain\ValueObjects\Call;
use Ai\Domain\ValueObjects\Chunk;
use Ai\Domain\ValueObjects\Quote;
use Ai\Domain\ValueObjects\ReasoningToken;
use Ai\Infrastructure\Services\AbstractBaseService;
use Ai\Infrastructure\Services\CostCalculator;
use Ai\Infrastructure\Services\Tools\CallException;
use Ai\Infrastructure\Services\Tools\ToolCollection;
use Billing\Domain\ValueObjects\CreditCount;
use File\Infrastructure\FileService;
use Generator;
use Override;
use Shared\Infrastructure\Services\ModelRegistry;
use Throwable;

class MessageService extends AbstractBaseService implements
    MessageServiceInterface
{
    public function __construct(
        private Client $client,
        private CostCalculator $calc,
        private FileService $fs,
        private ToolCollection $tools,
        private ModelRegistry $registry,
    ) {
        parent::__construct($registry, 'openai', 'llm');
    }

    #[Override]
    public function generateMessage(
        Model $model,
        MessageEntity $message
    ): Generator {
        $user = $message->getUser();

        $inputTokensCount = 0;
        $outputTokensCount = 0;
        $toolCost = new CreditCount(0);
        $files = [];

        $input = $this->buildInput(
            $message,
            $files
        );

        $body = [
            'safety_identifier' => (string) $user->getId()->getValue(),
            'prompt_cache_key' => (string) $user->getId()->getValue(),
            'input' => $input,
            'model' => $model->value,
            'stream' => true,
            "reasoning" => [
                "summary" => "auto"
            ]
        ];

        $tools = $this->getTools($message);
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }

        $continue = true;
        while ($continue) {
            $resp = $this->client->sendRequest('POST', '/v1/responses', $body);
            $stream = new StreamResponse($resp);

            $calls = [];
            $reasoning = []; // Add this to track reasoning items
            foreach ($stream as $data) {
                $type = $data->type ?? null;

                if ($type == 'response.completed') {
                    $inputTokensCount += $data->response->usage->input_tokens ?? 0;
                    $outputTokensCount += $data->response->usage->output_tokens ?? 0;
                    continue;
                }

                if ($type == 'response.output_text.delta') {
                    yield new Chunk($data->delta);
                    continue;
                }

                if ($type == 'response.reasoning_summary_text.delta') {
                    yield new Chunk(new ReasoningToken($data->delta));
                    continue;
                }

                // Add this block to capture reasoning items
                if ($type == 'response.output_item.done' && $data->item->type == 'reasoning') {
                    $reasoning[] = $data->item;
                    continue;
                }

                if ($type == 'response.output_item.done' && $data->item->type == 'function_call') {
                    $calls[] = $data->item;
                    continue;
                }
            }

            if ($calls) {
                // Merge both reasoning and calls into the input
                $body['input'] = array_merge($body['input'], $reasoning, $calls);
            }

            $continue = false;
            foreach ($calls as $call) {
                $tool = $this->tools->find($call->name);

                if (!$tool) {
                    continue;
                }

                $arguments = json_decode($call->arguments, true);
                yield new Chunk(new Call($call->name, $arguments));

                try {
                    $cr = $tool->call(
                        $message->getConversation(),
                        $message->getConversation()->getWorkspace(),
                        $message->getConversation()->getUser(),
                        $message->getAssistant(),
                        $arguments
                    );

                    $toolCost =  new CreditCount($cr->cost->value + $toolCost->value);

                    if ($cr->item) {
                        yield new Chunk($cr->item);
                    }

                    $content = $cr->content;
                } catch (CallException $th) {
                    $content = $th->getMessage();
                }

                $body['input'][] = [
                    'type' => 'function_call_output',
                    'status' => 'completed',
                    'output' => $content,
                    'call_id' => $call->call_id
                ];

                $continue = true;
            }
        }

        if ($this->client->hasCustomKey()) {
            // Cost is not calculated for custom keys,
            return new CreditCount(0);
        }

        $inputCost = $this->calc->calculate(
            $inputTokensCount,
            $model,
            CostCalculator::INPUT
        );

        $outputCost = $this->calc->calculate(
            $outputTokensCount,
            $model,
            CostCalculator::OUTPUT
        );

        return new CreditCount($inputCost->value + $outputCost->value + $toolCost->value);
    }

    private function buildInput(
        MessageEntity $message,
        array &$files = []
    ): array {
        $input = [];
        $current = $message;

        $imageCount = 0;
        while (true) {
            $role = $current->getRole()->value;
            $file = $current->getFile();

            if ($file) {
                $files[] = $file;
            }

            if ($current->getContent()->value) {
                if ($current->getQuote()->value) {
                    array_unshift(
                        $input,
                        $this->generateQuoteMessage($current->getQuote())
                    );
                }

                $content = [];
                $tokens = 0;
                $img = $current->getImage();

                if ($role == 'user' && $img) {
                    try {
                        $imgContent = $this->fs->getFileContents($img);

                        $content[] = [
                            'type' => 'input_image',
                            'detail' => 'auto',
                            'image_url' => 'data:'
                                . 'image/' .  $img->getExtension()
                                . ';base64,'
                                . base64_encode($imgContent)
                        ];

                        $imageCount++;
                        $tokens += $this->calcualteImageToken(
                            $img->getWidth()->value,
                            $img->getHeight()->value
                        );
                    } catch (Throwable $th) {
                        // Unable to load image
                    }
                }

                $content[] = [
                    'type' => $role == 'assistant' ? 'output_text' : 'input_text',
                    'text' => $current->getContent()->value
                ];

                array_unshift($input, [
                    'role' => $role,
                    'content' => $content,
                    'type' => 'message'
                ]);
            }

            if ($current->getParent()) {
                $current = $current->getParent();
                continue;
            }

            break;
        }

        $assistant = $message->getAssistant();
        if ($assistant) {
            if ($assistant->getInstructions()->value) {
                array_unshift($input, [
                    'role' => 'system',
                    'content' => $assistant->getInstructions()->value
                ]);
            }
        }

        // Add system instructions from tools
        foreach ($this->tools->getToolsForMessage($message) as $key => $tool) {
            $instructions = $tool->getSystemInstructions();
            if ($instructions) {
                $input[] = [
                    'role' => 'system',
                    'content' => $instructions,
                    'type' => 'message'
                ];
            }
        }

        return $input;
    }

    private function generateQuoteMessage(Quote $quote): array
    {
        return [
            'role' => 'system',
            'content' => 'The user is referring to this in particular:\n' . $quote->value,
            'type' => 'message'
        ];
    }

    private function calcualteImageToken(int $width, int $height): int
    {
        if ($width > 2048) {
            // Scale down to fit 2048x2048
            $width = 2048;
            $height = (int) (2048 / $width * $height);
        }

        if ($height > 2048) {
            // Scale down to fit 2048x2048
            $height = 2048;
            $width = (int) (2048 / $height * $width);
        }

        if ($width <= $height && $width > 768) {
            $width = 768;
            $height = (int) (768 / $width * $height);
        }

        // Calculate how many 512x512 tiles are needed to cover the image
        $tiles = (int) (ceil($width / 512) + ceil($height / 512));
        return 170 * $tiles + 85;
    }

    private function getTools(MessageEntity $message): array
    {
        $tools = [];

        foreach ($this->tools->getToolsForMessage($message) as $key => $tool) {
            $tools[] = [
                'type' => 'function',
                'name' => $key,
                'parameters' => $tool->getDefinitions(),
                'description' => $tool->getDescription(),
            ];
        }

        return $tools;
    }
}
