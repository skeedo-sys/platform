<?php

declare(strict_types=1);

namespace Import\Domain\Repositories;

use Import\Domain\Entities\ImportJobEntity;
use Import\Domain\Exceptions\ImportJobNotFoundException;
use Import\Domain\ValueObjects\ImportJobStatus;
use Import\Domain\ValueObjects\SortParameter;
use Iterator;
use Shared\Domain\Repositories\RepositoryInterface;
use Shared\Domain\ValueObjects\Id;
use Shared\Domain\ValueObjects\SortDirection;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

/**
 * Repository interface for ImportJobEntity.
 */
interface ImportJobRepositoryInterface extends RepositoryInterface
{
    /**
     * Add a new import job to the repository.
     *
     * @param ImportJobEntity $job
     * @return static
     */
    public function add(ImportJobEntity $job): static;

    /**
     * Remove an import job from the repository.
     *
     * @param ImportJobEntity $job
     * @return static
     */
    public function remove(ImportJobEntity $job): static;

    /**
     * Find an import job by its ID.
     *
     * @throws ImportJobNotFoundException
     * @return ImportJobEntity
     */
    public function ofId(Id $id): ImportJobEntity;

    /**
     * Get import jobs with a specific status.
     *
     * @param ImportJobStatus $status
     * @param int|null $limit
     * @return static
     */
    public function filterByStatus(
        ImportJobStatus $status
    ): static;

    /**
     * Get import jobs for a specific workspace.
     *
     * @param WorkspaceEntity|Id $workspace
     * @return static
     */
    public function filterByWorkspace(
        WorkspaceEntity|Id $workspace
    ): static;

    /**
     * Get import jobs for a specific user.
     *
     * @param UserEntity|Id $user
     * @return static
     */
    public function filterByUser(UserEntity|Id $user): static;

    /**
     * @param SortDirection $dir 
     * @param null|SortParameter $sortParameter
     * @return static 
     */
    public function sort(
        SortDirection $dir,
        ?SortParameter $sortParameter = null
    ): static;

    /**
     * @param ImportJobEntity $cursor 
     * @return Iterator<ImportJobEntity> 
     */
    public function startingAfter(ImportJobEntity $cursor): Iterator;

    /**
     * @param ImportJobEntity $cursor 
     * @return Iterator<ImportJobEntity> 
     */
    public function endingBefore(ImportJobEntity $cursor): Iterator;
}
