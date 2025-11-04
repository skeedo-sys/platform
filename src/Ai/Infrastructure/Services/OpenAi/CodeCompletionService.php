<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Ai\Domain\Completion\CodeCompletionServiceInterface;
use Ai\Domain\ValueObjects\Chunk;
use Ai\Domain\ValueObjects\Model;
use Ai\Infrastructure\Services\AbstractBaseService;
use Ai\Infrastructure\Services\CostCalculator;
use Billing\Domain\ValueObjects\CreditCount;
use Generator;
use Override;
use Shared\Infrastructure\Services\ModelRegistry;

class CodeCompletionService extends AbstractBaseService implements
    CodeCompletionServiceInterface
{
    public function __construct(
        private Client $client,
        private CostCalculator $calc,
        private ModelRegistry $registry,
    ) {
        parent::__construct($registry, 'openai', 'llm');
    }

    #[Override]
    public function generateCodeCompletion(
        Model $model,
        string $prompt,
        string $language,
        array $params = [],
    ): Generator {
        $resp = $this->client->sendRequest('POST', '/v1/responses', [
            'model' => $model->value,
            'instructions' => "You're $language programming language expert.",
            'input' => [
                [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt
                        ]
                    ]
                ],
            ],
            'temperature' => (int)($params['temperature'] ?? 1),
            'stream' => true
        ]);

        $inputTokensCount = 0;
        $outputTokensCount = 0;

        $stream = new StreamResponse($resp);
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
        }

        if ($this->client->hasCustomKey()) {
            // Cost is not calculated for custom keys,
            $cost = new CreditCount(0);
        } else {
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

            $cost = new CreditCount($inputCost->value + $outputCost->value);
        }

        return $cost;
    }
}
