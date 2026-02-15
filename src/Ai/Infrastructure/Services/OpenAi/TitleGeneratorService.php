<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Ai\Domain\Title\GenerateTitleResponse;
use Ai\Domain\Title\TitleServiceInterface;
use Ai\Domain\ValueObjects\Content;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\ValueObjects\Title;
use Ai\Infrastructure\Services\AbstractBaseService;
use Ai\Infrastructure\Utils\TextProcessor;
use Ai\Infrastructure\Services\CostCalculator;
use Billing\Domain\ValueObjects\CreditCount;
use Easy\Container\Attributes\Inject;
use Override;
use Shared\Infrastructure\Services\ModelRegistry;

class TitleGeneratorService extends AbstractBaseService implements
    TitleServiceInterface
{
    public function __construct(
        private Client $client,
        private CostCalculator $calc,
        private ModelRegistry $registry,

        #[Inject('option.billing.charge_for_titles')]
        private bool $chargeForTitles = true,
    ) {
        parent::__construct($registry, 'openai', 'llm');
    }

    #[Override]
    public function generateTitle(Content $content, Model $model): GenerateTitleResponse
    {
        $words = TextProcessor::sanitize($content);

        if (empty($words)) {
            $title = new Title();
            return new GenerateTitleResponse($title, new CreditCount(0));
        }

        $resp = $this->client->sendRequest('POST', '/v1/responses', [
            'model' => $model->value,
            'instructions' => TextProcessor::getSystemMessage(),
            'input' => [
                [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => TextProcessor::getUserMessage($words),
                        ]
                    ]
                ]
            ]
        ]);

        $data = json_decode($resp->getBody()->getContents());

        if ($this->client->hasCustomKey() || !$this->chargeForTitles) {
            // Cost is not calculated for custom keys or when disabled
            $cost = new CreditCount(0);
        } else {
            $inputCost = $this->calc->calculate(
                $data->usage->input_tokens ?? 0,
                $model,
                CostCalculator::INPUT
            );

            $outputCost = $this->calc->calculate(
                $data->usage->output_tokens ?? 0,
                $model,
                CostCalculator::OUTPUT
            );

            $cost = new CreditCount($inputCost->value + $outputCost->value);
        }

        $title = '';
        $output = $data->output;
        foreach ($output as $item) {
            if ($item->type == 'message' && $item->role == 'assistant') {
                $title = $item->content[0]->text;
                break;
            }
        }

        $title = explode("\n", trim($title))[0];
        $title = trim($title, ' "');

        return new GenerateTitleResponse(
            new Title($title ?: null),
            $cost
        );
    }
}
