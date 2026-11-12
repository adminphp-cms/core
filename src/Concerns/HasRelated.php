<?php

namespace Gmarineau\AdminPhp\Concerns;

use Gmarineau\AdminPhp\Models\Related;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model read access to the contents chosen in its Browser fields, e.g. to render them on the site.
 */
trait HasRelated
{
    /**
     * Rows of all the Browser fields of the model.
     *
     * @return MorphMany<Related, $this>
     */
    public function relatedItems(): MorphMany
    {
        return $this->morphMany(Related::class, 'subject')->orderBy('position');
    }

    /**
     * Contents chosen in the given Browser field, in their chosen order. Only the ones
     * the site may show are returned, unless $onlyPublished is false.
     *
     * @return Collection<int, Model>
     */
    public function related(string $browser, bool $onlyPublished = true): Collection
    {
        $references = $this->relatedItems()
            ->where('browser_name', $browser)
            ->get(['related_type', 'related_id'])
            ->map(fn (Related $related): array => ['type' => $related->related_type, 'id' => $related->related_id])
            ->all();

        return Related::resolve($references, $onlyPublished);
    }
}
