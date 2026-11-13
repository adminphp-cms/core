<?php

namespace AdminPhp\Actions;

use AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Validates the submitted form of a module and persists it: model columns first,
 * then what each field stores outside the model (blocks…). Fields absent from
 * the input are left untouched.
 */
class SaveModuleContent
{
    public function __construct(protected SyncMediaUsages $syncMediaUsages) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Module $module, array $input, ?Model $model = null): Model
    {
        $modelClass = $module->modelClass();
        $model ??= new $modelClass;

        if (! $model instanceof $modelClass) {
            throw new InvalidArgumentException(sprintf(
                'Module [%s] manages [%s] models, [%s] given.',
                $module->name(),
                $modelClass,
                $model::class,
            ));
        }

        $validated = $module->validate($input, onlyPresentFields: $model->exists);

        $fields = array_intersect_key($module->fields(), $validated);

        return $model->getConnection()->transaction(function () use ($module, $model, $fields, $validated): Model {
            foreach ($fields as $name => $field) {
                $field->fillModel($model, $validated[$name] ?? null);
            }

            foreach ($module->fields() as $field) {
                $field->beforeModelSave($model);
            }

            $model->save();

            foreach ($fields as $name => $field) {
                $field->afterModelSaved($model, $validated[$name] ?? null);
            }

            $this->syncMediaUsages->forItem($module, $model);

            return $model;
        });
    }
}
