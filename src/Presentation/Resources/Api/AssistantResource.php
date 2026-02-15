<?php

declare(strict_types=1);

namespace Presentation\Resources\Api;

use Assistant\Domain\Entities\AssistantEntity;
use Billing\Domain\Entities\SubscriptionEntity;
use Dataset\Domain\Entities\FileUnitEntity;
use Dataset\Domain\Entities\LinkUnitEntity;
use JsonSerializable;
use Override;
use Presentation\Resources\DateTimeResource;

class AssistantResource implements JsonSerializable
{
    use Traits\TwigResource;

    public function __construct(
        private AssistantEntity $assistant,
        private ?SubscriptionEntity $subscription = null,
        private array $extend = []
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $res = $this->assistant;

        $dataset = [];

        if (in_array('dataset', $this->extend)) {
            foreach ($res->getDataset() as $unit) {
                match (true) {
                    $unit instanceof FileUnitEntity => $dataset[] = new FileUnitResource($unit),
                    $unit instanceof LinkUnitEntity => $dataset[] = new LinkUnitResource($unit),
                    default => $dataset = [],
                };
            }
        }

        $data = [
            'object' => 'assistant',
            'id' => $res->getId(),
            'name' => $res->getName(),
            'expertise' => $res->getExpertise(),
            'description' => $res->getDescription(),
            'avatar' => $res->getAvatar(),
            'model' => $res->getModel(),
            'dataset' => $dataset,
            'created_at' => new DateTimeResource($res->getCreatedAt()),
            'updated_at' => new DateTimeResource($res->getUpdatedAt()),
            'visibility' => $res->getVisibility(),
            'user' => $res->getUser() ? $res->getUser()->getId() : null,
            'workspace' => $res->getWorkspace() ? $res->getWorkspace()->getId() : null,
            'granted' => false,
        ];

        if (in_array('instructions', $this->extend)) {
            $data['instructions'] = $res->getInstructions();
        }

        if ($this->subscription) {
            $config = $this->subscription->getPlan()->getConfig();
            $data['granted'] = is_null($config->assistants) || in_array(
                (string)$this->assistant->getId(),
                $config->assistants
            );
        }

        if (in_array('user', $this->extend) && $res->getUser()) {
            $data['user'] = new UserResource($res->getUser());
        }

        if (in_array('workspace', $this->extend) && $res->getWorkspace()) {
            $data['workspace'] = new WorkspaceResource($res->getWorkspace());
        }

        return $data;
    }
}
