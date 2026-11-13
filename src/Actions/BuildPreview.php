<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the item shown in a preview from the values of the admin form, saved or not:
 * an in-memory copy of the stored item (or a new one) that is never written. Values are
 * not validated, so an incomplete form can still be previewed.
 */
class BuildPreview
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Module $module, array $values, ?Model $item = null): Model
    {
        $modelClass = $module->modelClass();

        $preview = $item === null ? new $modelClass : $item->newInstance([], true);

        if ($item !== null) {
            $preview->setRawAttributes($item->getAttributes(), true);
        }

        foreach ($module->fields() as $name => $field) {
            if (array_key_exists($name, $values)) {
                $field->fillPreview($preview, $values[$name]);
            }
        }

        foreach ($module->fields() as $field) {
            $field->beforeModelSave($preview);
        }

        return $preview;
    }
}
