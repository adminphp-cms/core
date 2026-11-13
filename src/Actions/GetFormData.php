<?php

namespace AdminPhp\Actions;

use AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;

/**
 * Reads a model back into the shape of its module form.
 */
class GetFormData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Module $module, Model $model): array
    {
        $data = [];

        foreach ($module->fields() as $name => $field) {
            $data[$name] = $field->valueFromModel($model);
        }

        return $data;
    }
}
