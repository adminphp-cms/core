<?php

namespace Gmarineau\AdminPhp\Fields;

use Gmarineau\AdminPhp\Actions\SyncRelated;
use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Gmarineau\AdminPhp\Models\Related;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Contents of other modules chosen and ordered by hand (related articles, featured pages…).
 * The value is a list of references: [['type' => 'article', 'id' => '01J…'], …], where the
 * type is the module name. On a module, the references are stored in the adminphp_related
 * table (read them with HasRelated::related()); in a block, a repeater or the settings,
 * they are stored with the rest of the content.
 */
class Browser extends Field
{
    use IsNotTranslatable;

    /**
     * @var array<int, class-string<Module>>
     */
    protected array $moduleClasses = [];

    protected ?int $max = null;

    public function type(): string
    {
        return 'browser';
    }

    public function listingType(): ?string
    {
        return null;
    }

    /**
     * Modules whose contents can be chosen.
     *
     * @param  array<int, class-string<Module>>  $moduleClasses
     */
    public function modules(array $moduleClasses): static
    {
        $this->moduleClasses = $moduleClasses;

        return $this;
    }

    /**
     * Maximum number of contents, e.g. 1 to choose a single one.
     */
    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * @return array<string, Module>
     */
    public function getModules(): array
    {
        $modules = [];

        foreach ($this->moduleClasses as $moduleClass) {
            $module = new $moduleClass;
            $modules[$module->name()] = $module;
        }

        return $modules;
    }

    public function validationRules(string $path, mixed $value): array
    {
        $modules = $this->getModules();

        $rules = [
            $path => [
                $this->isRequired ? 'required' : 'nullable',
                'array',
                'list',
                ...($this->max !== null ? ["max:{$this->max}"] : []),
                ...$this->rules,
            ],
            "{$path}.*" => ['array'],
            "{$path}.*.type" => ['required', 'string', Rule::in(array_keys($modules))],
            "{$path}.*.id" => ['required', 'string', 'ulid', 'distinct'],
        ];

        foreach (is_array($value) ? $value : [] as $index => $reference) {
            $module = is_array($reference) && is_string($reference['type'] ?? null) ? ($modules[$reference['type']] ?? null) : null;

            if ($module !== null) {
                $model = new ($module->modelClass());

                $rules["{$path}.{$index}.id"] = [Rule::exists($model->getTable(), $model->getKeyName())];
            }
        }

        return $rules;
    }

    public function validationAttributes(string $path, mixed $value): array
    {
        $attributes = [$path => $this->getLabel()];

        foreach (is_array($value) ? array_keys($value) : [] as $index) {
            $attributes["{$path}.{$index}.type"] = $this->getLabel();
            $attributes["{$path}.{$index}.id"] = $this->getLabel();
        }

        return $attributes;
    }

    /**
     * @return array<int, array{type: string, id: string}>
     */
    public function dehydrate(mixed $value): array
    {
        $references = [];

        foreach (is_array($value) ? $value : [] as $reference) {
            if (is_array($reference) && is_string($reference['type'] ?? null) && is_string($reference['id'] ?? null)) {
                $references[] = ['type' => $reference['type'], 'id' => $reference['id']];
            }
        }

        return $references;
    }

    public function fillModel(Model $model, mixed $value): void {}

    public function afterModelSaved(Model $model, mixed $value): void
    {
        app(SyncRelated::class)->handle($model, $this, $this->dehydrate($value));
    }

    /**
     * @return array<int, array{type: string, id: string}>
     */
    public function valueFromModel(Model $model): array
    {
        return Related::query()
            ->whereMorphedTo('subject', $model)
            ->where('browser_name', $this->name)
            ->orderBy('position')
            ->get(['related_type', 'related_id'])
            ->map(fn (Related $related): array => ['type' => $related->related_type, 'id' => $related->related_id])
            ->all();
    }

    protected function schemaAttributes(): array
    {
        return [
            'modules' => array_values(array_map(fn (Module $module): array => [
                'name' => $module->name(),
                'title' => $module->title(),
                'icon' => $module->icon(),
            ], $this->getModules())),
            'max' => $this->max,
        ];
    }
}
