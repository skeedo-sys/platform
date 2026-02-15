<?php

declare(strict_types=1);

namespace Import\Infrastructure\Repositories\DoctrineOrm;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Import\Domain\Entities\ImportJobEntity;
use Import\Domain\Exceptions\ImportJobNotFoundException;
use Import\Domain\Repositories\ImportJobRepositoryInterface;
use Import\Domain\ValueObjects\ImportJobStatus;
use Import\Domain\ValueObjects\SortParameter;
use Iterator;
use Override;
use Shared\Domain\ValueObjects\Id;
use Shared\Domain\ValueObjects\SortDirection;
use Shared\Infrastructure\Repositories\DoctrineOrm\AbstractRepository;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

/**
 * Doctrine ORM implementation of ImportJobRepositoryInterface.
 */
class ImportJobRepository extends AbstractRepository implements ImportJobRepositoryInterface
{
    private const ENTITY_CLASS = ImportJobEntity::class;
    private const ALIAS = 'import_job';
    private ?SortParameter $sortParameter = null;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, self::ENTITY_CLASS, self::ALIAS);
    }

    #[Override]
    public function add(ImportJobEntity $job): static
    {
        $this->em->persist($job);
        return $this;
    }

    #[Override]
    public function remove(ImportJobEntity $job): static
    {
        $this->em->remove($job);
        return $this;
    }

    #[Override]
    public function ofId(Id $id): ImportJobEntity
    {
        $object = $this->em->find(self::ENTITY_CLASS, $id);

        if ($object instanceof ImportJobEntity) {
            return $object;
        }

        throw new ImportJobNotFoundException($id);
    }

    #[Override]
    public function filterByStatus(
        ImportJobStatus $status
    ): static {
        return $this->filter(static function (QueryBuilder $qb) use ($status) {
            $qb->andWhere(self::ALIAS . '.status = :status')
                ->setParameter(':status', $status->value, Types::STRING);
        });
    }

    #[Override]
    public function filterByWorkspace(
        WorkspaceEntity|Id $workspace
    ): static {
        $id = $workspace instanceof WorkspaceEntity
            ? $workspace->getId()
            : $workspace;

        return $this->filter(static function (QueryBuilder $qb) use ($id) {
            $qb->andWhere(self::ALIAS . '.workspace = :workspace')
                ->setParameter(
                    ':workspace',
                    $id->getValue()->getBytes(),
                    Types::STRING
                );
        });
    }

    #[Override]
    public function filterByUser(
        UserEntity|Id $user
    ): static {
        $id = $user instanceof UserEntity
            ? $user->getId()
            : $user;

        return $this->filter(static function (QueryBuilder $qb) use ($id) {
            $qb->andWhere(self::ALIAS . '.user = :user')
                ->setParameter(':user', $id->getValue()->getBytes(), Types::STRING);
        });
    }

    #[Override]
    public function sort(
        SortDirection $dir,
        ?SortParameter $sortParameter = null
    ): static {
        $cloned = $this->doSort($dir, $this->getSortKey($sortParameter));
        $cloned->sortParameter = $sortParameter;

        return $cloned;
    }

    #[Override]
    public function startingAfter(ImportJobEntity $cursor): Iterator
    {
        return $this->doStartingAfter(
            $cursor->getId(),
            $this->getCompareValue($cursor)
        );
    }

    #[Override]
    public function endingBefore(ImportJobEntity $cursor): Iterator
    {
        return $this->doEndingBefore(
            $cursor->getId(),
            $this->getCompareValue($cursor)
        );
    }

    /**
     * Returns the sort key based on the given SortParameter.
     *
     * @param SortParameter|null $param The SortParameter to determine the 
     * sort key.
     * @return string|null The sort key corresponding to the given SortParameter, 
     * or null if the SortParameter is not recognized.
     */
    private function getSortKey(
        ?SortParameter $param
    ): ?string {
        return match ($param) {
            SortParameter::ID => 'id.value',
            SortParameter::CREATED_AT => 'createdAt',
            SortParameter::UPDATED_AT => 'updatedAt',
            default => null
        };
    }

    /**
     * Get the compare value based on the sort parameter.
     *
     * @param ImportJobEntity $cursor The user entity to compare.
     * @return null|string|DateTimeInterface The compare value based on the 
     * sort parameter.
     */
    private function getCompareValue(
        ImportJobEntity $cursor
    ): null|int|string|DateTimeInterface {
        return match ($this->sortParameter) {
            SortParameter::ID => $cursor->getId()->getValue()->getBytes(),
            SortParameter::CREATED_AT => $cursor->getCreatedAt(),
            SortParameter::UPDATED_AT => $cursor->getUpdatedAt(),
            default => null
        };
    }
}
