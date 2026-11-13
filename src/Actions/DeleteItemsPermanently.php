<?php

namespace AdminPhp\Actions;

use AdminPhp\Models\Block;
use AdminPhp\Models\Lock;
use AdminPhp\Models\Related;
use AdminPhp\Models\Revision;
use AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Empties items out of the trash for good, with what AdminPhp stored for them: their
 * blocks, revisions, image usages and Browser choices, and the choices of other contents pointing to them.
 * Only trashed items can be deleted this way.
 */
class DeleteItemsPermanently
{
    public function __construct(protected SyncMediaUsages $syncMediaUsages) {}

    /**
     * @param  array<int, string>  $ids
     * @return int How many items were deleted.
     */
    public function handle(Module $module, array $ids): int
    {
        if (! $module->isTrashable()) {
            throw new LogicException(sprintf('Module [%s] has no trash: add SoftDeletes to its model.', $module->name()));
        }

        $items = $module->modelClass()::query()->onlyTrashed()->whereKey($ids)->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $items->first()->getConnection()->transaction(function () use ($module, $items): void {
            foreach ($items as $item) {
                Block::query()->whereMorphedTo('blockable', $item)->delete();
                Revision::query()->whereMorphedTo('revisionable', $item)->delete();
                Lock::query()->whereMorphedTo('lockable', $item)->delete();
                $this->syncMediaUsages->forget($module, $item);
                Related::query()
                    ->where(fn (Builder $query) => $query->whereMorphedTo('subject', $item)->orWhereMorphedTo('related', $item))
                    ->delete();

                /** @var Model $item */
                $item->forceDelete();
            }
        });

        return $items->count();
    }
}
