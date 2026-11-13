<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Applies an action of the listing to several items at once. An item that cannot take the
 * action (a draft that no longer validates, for instance) is skipped and reported.
 */
class RunBulkAction
{
    public const ACTIONS = ['publish', 'unpublish', 'trash', 'restore', 'delete'];

    public function __construct(
        protected PublishItem $publishItem,
        protected UnpublishItem $unpublishItem,
        protected DeleteItemsPermanently $deleteItemsPermanently,
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

        if ($action === 'delete') {
            return ['done' => $this->deleteItemsPermanently->handle($module, $ids), 'failed' => []];
        }

        if ($action === 'restore') {
            return ['done' => $query->onlyTrashed()->whereKey($ids)->get()->each->restore()->count(), 'failed' => []];
        }

        if ($action === 'trash') {
            return ['done' => $query->whereKey($ids)->get()->each->delete()->count(), 'failed' => []];
        }

        $done = 0;
        $failed = [];

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
