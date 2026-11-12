<?php

namespace Gmarineau\AdminPhp\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lets items of a module be nested under other items of the same module (menu links,
 * pages…), up to the depth declared on the module. Use it with HasPosition; its table
 * needs the column added by the `$table->nesting()` migration macro.
 *
 * @property string|null $parent_id
 */
trait HasNesting
{
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
