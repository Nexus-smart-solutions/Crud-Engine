<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Crud;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Repositories\RepositoryFactory;

/**
 * Replaces `App\Core\Classes\DeletingData\BulkDestroyService`.
 *
 * Fixes Bug 4.5 from the Phase 1 audit: the original called
 * `array_filter($ids, 'is_numeric')` on `request()->input('ids') ?? []`
 * with no check that the input was actually an array — a scalar `ids`
 * value (e.g. `?ids=5`) threw a `TypeError`. {@see resolveIds()} below
 * normalizes non-array input into a single-element array first.
 *
 * `Request` is constructor-injected here (proper DI) rather than read
 * via the global `request()` helper, so this class is testable by
 * passing in a fake request instance.
 */
abstract class AbstractBulkDeleteService extends AbstractDeleteService
{
    public function __construct(
        FileLifecycleServiceInterface $files,
        CapabilityRegistryInterface $capabilities,
        Dispatcher $events,
        private readonly RepositoryFactory $repositoryFactory,
        private readonly Request $request,
    ) {
        parent::__construct($files, $capabilities, $events);
    }

    public function resolveTargets(): Collection
    {
        $repository = $this->repositoryFactory->make($this->model());

        return $repository->findManyByIds($this->resolveIds());
    }

    /**
     * @return array<int, int|string>
     */
    protected function resolveIds(): array
    {
        $rawIds = $this->request->input('ids', []);

        if (! is_array($rawIds)) {
            $rawIds = [$rawIds];
        }

        return array_values(array_filter($rawIds, static fn ($id) => is_numeric($id)));
    }
}
