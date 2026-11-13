<?php

namespace AdminPhp\Actions;

use AdminPhp\Fields\Browser;
use AdminPhp\Models\Related;
use Illuminate\Database\Eloquent\Model;

/**
 * Makes the stored rows of a Browser field match the submitted references, in their order.
 */
class SyncRelated
{
    /**
     * @param  array<int, array{type: string, id: string}>  $references
     */
    public function handle(Model $model, Browser $field, array $references): void
    {
        Related::query()
            ->whereMorphedTo('subject', $model)
            ->where('browser_name', $field->name)
            ->delete();

        foreach (array_values($references) as $position => $reference) {
            $related = new Related([
                'browser_name' => $field->name,
                'related_type' => $reference['type'],
                'related_id' => $reference['id'],
                'position' => $position,
            ]);

            $related->subject()->associate($model)->save();
        }
    }
}
