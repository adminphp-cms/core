<?php

namespace Gmarineau\AdminPhp\Fields;

use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * A list of items sharing the same fields (questions of a FAQ, speakers…), stored as JSON:
 * [['id' => '01J…', 'question' => ['fr' => …], …], …]. On a module it needs a json column
 * cast to array; in a block or the settings it is stored with the rest of the content.
 * Translate the fields of the items rather than the repeater itself.
 */
class Repeater extends Field
{
    use IsNotTranslatable;

    /**
     * @var array<int, Field>
     */
    protected array $fields = [];

    protected ?int $min = null;

    protected ?int $max = null;

    protected ?string $itemLabel = null;

    protected ?string $addLabel = null;

    public function type(): string
    {
        return 'repeater';
    }

    public function listingType(): ?string
    {
        return null;
    }

    /**
     * Fields of each item.
     *
     * @param  array<int, Field>  $fields
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $field) {
            if ($field instanceof Blocks || $field instanceof Slug) {
                throw new LogicException(sprintf('Repeater [%s] cannot contain a %s field.', $this->name, class_basename($field)));
            }
        }

        $this->fields = $fields;

        return $this;
    }

    public function min(int $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->max = $max;

        return $this;
    }

    /**
     * Field whose value names a folded item in the admin (the first text field by default).
     */
    public function itemLabel(string $fieldName): static
    {
        $this->itemLabel = $fieldName;

        return $this;
    }

    /**
     * Text of the "add" button, e.g. "Ajouter une question".
     */
    public function addLabel(string $addLabel): static
    {
        $this->addLabel = $addLabel;

        return $this;
    }

    /**
     * @return array<string, Field>
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    public function validationRules(string $path, mixed $value): array
    {
        $rules = [
            $path => [
                $this->isRequired ? 'required' : 'nullable',
                'array',
                'list',
                ...($this->min !== null ? ["min:{$this->min}"] : []),
                ...($this->max !== null ? ["max:{$this->max}"] : []),
                ...$this->rules,
            ],
            "{$path}.*" => ['array'],
            "{$path}.*.id" => ['nullable', 'string', 'ulid', 'distinct'],
        ];

        foreach (is_array($value) ? $value : [] as $index => $item) {
            $item = is_array($item) ? $item : [];

            foreach ($this->getFields() as $name => $field) {
                $rules = [...$rules, ...$field->validationRules("{$path}.{$index}.{$name}", $item[$name] ?? null)];
            }
        }

        return $rules;
    }

    /**
     * Item fields read "FAQ 2 · Question (EN)" in messages.
     */
    public function validationAttributes(string $path, mixed $value): array
    {
        $attributes = [$path => $this->getLabel()];

        foreach (is_array($value) ? $value : [] as $index => $item) {
            $item = is_array($item) ? $item : [];
            $prefix = sprintf('%s %d · ', $this->getLabel(), (int) $index + 1);

            foreach ($this->getFields() as $name => $field) {
                foreach ($field->validationAttributes("{$path}.{$index}.{$name}", $item[$name] ?? null) as $attributePath => $attribute) {
                    $attributes[$attributePath] = $prefix.$attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * Keeps only the declared fields of each item, normalized for storage. Every item
     * keeps an id (a new ULID if it has none) so it can be followed across saves.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dehydrate(mixed $value): array
    {
        $items = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $item = is_array($item) ? $item : [];

            $dehydrated = ['id' => is_string($item['id'] ?? null) && $item['id'] !== '' ? $item['id'] : (string) Str::ulid()];

            foreach ($this->getFields() as $name => $field) {
                $dehydrated[$name] = $field->dehydrate($item[$name] ?? null);
            }

            $items[] = $dehydrated;
        }

        return $items;
    }

    public function assetIds(mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $item) {
            foreach ($this->getFields() as $name => $field) {
                $ids = [...$ids, ...$field->assetIds(is_array($item) ? ($item[$name] ?? null) : null)];
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function valueFromModel(Model $model): array
    {
        $value = $model->getAttribute($this->name);

        return is_array($value) ? array_values($value) : [];
    }

    protected function schemaAttributes(): array
    {
        $fields = $this->getFields();

        return [
            'fields' => array_values(array_map(fn (Field $field): array => $field->toSchema(), $fields)),
            'min' => $this->min,
            'max' => $this->max,
            'itemLabel' => $this->itemLabel ?? $this->defaultItemLabel($fields),
            'addLabel' => $this->addLabel ?? 'Ajouter',
        ];
    }

    /**
     * @param  array<string, Field>  $fields
     */
    protected function defaultItemLabel(array $fields): ?string
    {
        foreach ($fields as $name => $field) {
            if ($field instanceof Text) {
                return $name;
            }
        }

        return null;
    }
}
