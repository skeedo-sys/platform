<?php

declare(strict_types=1);

namespace Presentation\Resources\Admin\Api;

use Assistant\Domain\Entities\AssistantEntity;
use Dataset\Domain\Entities\FileUnitEntity;
use Dataset\Domain\Entities\LinkUnitEntity;
use JsonSerializable;
use Override;
use Presentation\Resources\Api\Traits\TwigResource;
use Presentation\Resources\DateTimeResource;

class AssistantResource implements JsonSerializable
{
    use TwigResource;

    public function __construct(
        private AssistantEntity $assistant,
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
            'instructions' => $res->getInstructions(),
            'avatar' => $res->getAvatar(),
            'model' => $res->getModel(),
            'status' => $res->getStatus(),
            'created_at' => new DateTimeResource($res->getCreatedAt()),
            'updated_at' => new DateTimeResource($res->getUpdatedAt()),
            'dataset' => $dataset,
            'user' => $res->getUser() ? $res->getUser()->getId() : null,
            'workspace' => $res->getWorkspace() ? $res->getWorkspace()->getId() : null,
            'visibility' => $res->getVisibility(),
        ];

        if (in_array('user', $this->extend) && $res->getUser()) {
            $data['user'] = new UserResource($res->getUser());
        }

        if (in_array('workspace', $this->extend) && $res->getWorkspace()) {
            $data['workspace'] = new WorkspaceResource($res->getWorkspace());
        }

        return $data;
    }
}
