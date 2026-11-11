<?php

namespace Gmarineau\AdminPhp\Modules;

use Gmarineau\AdminPhp\Concerns\HasPublication;
use Gmarineau\AdminPhp\Fields\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class Module
{
    /**
     * @var class-string<Model>
     */
    protected string $model;

    /**
     * Label shown in the admin navigation (defaults to the plural of the name).
     */
    protected string $title = '';

    /**
     * Field used to name an item in listings.
     */
    protected string $titleField = 'title';

    /**
     * Short sentence shown under the module title in the admin.
     */
    protected string $description = '';

    /**
     * Icon of the module in the admin: a Lucide icon name in kebab case
     * (file-text, newspaper, book-open, calendar, image, users, tag, briefcase…).
     */
    protected string $icon = 'file-text';

    /**
     * @return array<int, Field>
     */
    abstract public function form(): array;

    /**
     * Identifier of the module, also used as the model's morph map alias (ArticleModule => article).
     */
    public function name(): string
    {
        return Str::kebab(Str::replaceEnd('Module', '', class_basename(static::class)));
    }

    public function title(): string
    {
        return $this->title !== '' ? $this->title : Str::headline(Str::plural($this->name()));
    }

    public function description(): string
    {
        return $this->description;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    /**
     * Name of an item in listings, in the given locale with a fallback on the other locales.
     */
    public function itemTitle(Model $model, string $locale): string
    {
        $value = ($this->fields()[$this->titleField] ?? null)?->valueFromModel($model);

        if (is_array($value)) {
            $value = $value[$locale] ?? (array_values(array_filter($value))[0] ?? null);
        }

        return is_scalar($value) && $value !== '' ? (string) $value : (string) $model->getKey();
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return $this->model;
    }

    /**
     * Whether the model goes through drafts, publication and revisions (HasPublication).
     */
    public function isPublishable(): bool
    {
        return in_array(HasPublication::class, class_uses_recursive($this->model), true);
    }

    /**
     * Validates a submitted form and returns the values of the module fields only.
     *
     * @param  array<string, mixed>  $input
     * @param  bool  $onlyPresentFields  Validate only the fields present in the input (partial update).
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, bool $onlyPresentFields = false): array
    {
        $validated = Validator::make(
            $input,
            $this->validationRules($input, $onlyPresentFields),
            attributes: $this->validationAttributes($input),
        )->validate();

        return array_intersect_key($validated, $this->fields());
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->form() as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  bool  $onlyPresentFields  Validate only the fields present in the input (partial update).
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $input, bool $onlyPresentFields = false): array
    {
        $rules = [];

        foreach ($this->fields() as $name => $field) {
            if ($onlyPresentFields && ! array_key_exists($name, $input)) {
                continue;
            }

            $rules = [...$rules, ...$field->validationRules($name, $input[$name] ?? null)];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function validationAttributes(array $input): array
    {
        $attributes = [];

        foreach ($this->fields() as $name => $field) {
            $attributes = [...$attributes, ...$field->validationAttributes($name, $input[$name] ?? null)];
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'publishable' => $this->isPublishable(),
            'fields' => array_values(array_map(fn (Field $field): array => $field->toSchema(), $this->fields())),
        ];
    }
}
