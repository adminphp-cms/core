<?php

namespace Gmarineau\AdminPhp\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lets editors order the items of a module by hand (drag and drop in the admin listing).
 * Its table needs the column added by the `$table->position()` migration macro.
 * A new item goes last among its siblings.
 *
 * @property int $position
 */
trait HasPosition
{
    public static function bootHasPosition(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('position') !== null) {
                return;
            }

            $siblings = $model->newQuery();

            if (in_array(HasNesting::class, class_uses_recursive($model), true)) {
                $siblings->where('parent_id', $model->getAttribute('parent_id'));
            }

            $model->setAttribute('position', ((int) $siblings->max('position')) + 1);
        });
    }

    public function initializeHasPosition(): void
    {
        $this->mergeCasts(['position' => 'integer']);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('position'));
    }
}
