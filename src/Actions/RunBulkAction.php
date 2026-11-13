<?php

namespace AdminPhp\Actions;

use AdminPhp\AdminPhp;
use AdminPhp\Models\Lock;
use AdminPhp\Modules\Module;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Applies an action of the listing to several items at once. An item that cannot take the
 * action (a draft that no longer validates, an item someone else is editing) is skipped
 * and reported.
 */
class RunBulkAction
{
    public const ACTIONS = ['publish', 'unpublish', 'trash', 'restore', 'delete'];

    public function __construct(
        protected PublishItem $publishItem,
        protected UnpublishItem $unpublishItem,
        protected DeleteItemsPermanently $deleteItemsPermanently,
        protected ManageEditLock $manageEditLock,
        protected AdminPhp $adminPhp,
    ) {}

    /**
     * @param  array<int, string>  $ids
     * @return array{done: int, failed: array<int, string>}
     *
     * @throws InvalidArgumentException When the module does not support the action.
     */
    public function handle(Module $module, string $action, array $ids): array
    {
        $this->ensureSupported($module, $action);

        $query = $module->modelClass()::query();
        $failed = $this->idsLockedByOthers($module, $ids);
        $ids = array_values(array_diff($ids, $failed));

        if ($action === 'delete') {
            return ['done' => $this->deleteItemsPermanently->handle($module, $ids), 'failed' => $failed];
        }

        if ($action === 'restore') {
            return ['done' => $query->onlyTrashed()->whereKey($ids)->get()->each->restore()->count(), 'failed' => $failed];
        }

        if ($action === 'trash') {
            return ['done' => $query->whereKey($ids)->get()->each->delete()->count(), 'failed' => $failed];
        }

        $done = 0;

        foreach ($query->whereKey($ids)->get() as $item) {
            try {
                $action === 'publish' ? $this->publishItem->handle($module, $item) : $this->unpublishItem->handle($module, $item);
                $done++;
            } catch (ValidationException) {
                $failed[] = (string) $item->getKey();
            }
        }

        return ['done' => $done, 'failed' => $failed];
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    protected function idsLockedByOthers(Module $module, array $ids): array
    {
        $query = $module->modelClass()::query();

        if ($module->isTrashable()) {
            $query->withTrashed();
        }

        $locks = $this->manageEditLock->activeLocks($query->whereKey($ids)->get()->all());
        $userId = $this->adminPhp->user()?->getKey();

        return array_values(array_map(
            fn (Lock $lock): string => $lock->lockable_id,
            array_filter($locks, fn (Lock $lock): bool => $lock->user_id !== $userId),
        ));
    }

    protected function ensureSupported(Module $module, string $action): void
    {
        $isSupported = match ($action) {
            'publish', 'unpublish' => $module->isPublishable(),
            'trash', 'restore', 'delete' => $module->isTrashable(),
            default => false,
        };

        if (! $isSupported) {
            throw new InvalidArgumentException(sprintf('Module [%s] does not support the [%s] action.', $module->name(), $action));
        }
    }
}
