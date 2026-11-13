<?php

namespace AdminPhp\Concerns;

use AdminPhp\Models\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model read access to its blocks, e.g. to render them on the site.
 */
trait HasBlocks
{
    /**
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')
            ->whereNull('parent_id')
            ->orderBy('position');
    }
}
