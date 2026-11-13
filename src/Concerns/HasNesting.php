<?php

namespace Gmarineau\AdminPhp\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lets items of a module be nested under other items of the same module (menu links,
 * pages…), up to the depth declared on the module. Use it with HasPosition; its table
 * needs the column added by the `$table->nesting()` migration macro.
 *
 * With SoftDeletes, an item goes to the trash with its descendants and comes back with
 * the ones trashed along with it. An item restored without its parent (still in the trash
 * or deleted) moves to the top of the tree, so it never hangs under a missing item.
 *
 * @property string|null $parent_id
 */
trait HasNesting
{
    /**
     * When the item being restored was trashed, to bring back the children trashed with it.
     */
    protected ?CarbonInterface $trashedAtBeforeRestore = null;

    public static function bootHasNesting(): void
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            return;
        }

        static::softDeleted(function (self $model): void {
            $model->children()->get()->each->delete();
        });

        static::restoring(function (self $model): void {
            $model->trashedAtBeforeRestore = $model->getAttribute($model->getDeletedAtColumn());

            $parentId = $model->getAttribute('parent_id');

            if ($parentId !== null && ! $model->newQuery()->whereKey($parentId)->exists()) {
                $model->setAttribute('parent_id', null);

                if (in_array(HasPosition::class, class_uses_recursive($model), true)) {
                    $model->setAttribute('position', ((int) $model->newQuery()->whereNull('parent_id')->max('position')) + 1);
                }
            }
        });

        static::restored(function (self $model): void {
            if ($model->trashedAtBeforeRestore === null) {
                return;
            }

            $model->newQuery()
                ->onlyTrashed()
                ->where('parent_id', $model->getKey())
                ->where($model->getDeletedAtColumn(), '>=', $model->trashedAtBeforeRestore)
                ->get()
                ->each->restore();

            $model->trashedAtBeforeRestore = null;
        });
    }

    /**
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * @return HasMany<static, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('position');
    }

    /**
     * Items at the top of the tree.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('parent_id'));
    }
}
