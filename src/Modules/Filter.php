<?php

namespace AdminPhp\Modules;

use AdminPhp\Fields\Select;
use AdminPhp\Fields\Toggle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A filter of a module listing, made from a Select or Toggle field of the form.
 */
class Filter
{
    protected ?string $label = null;

    final public function __construct(public readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function options(Module $module): array
    {
        $field = $this->field($module);

        if ($field instanceof Toggle) {
            return [['value' => '1', 'label' => 'Oui'], ['value' => '0', 'label' => 'Non']];
        }

        return collect($field->resolvedOptions())
            ->map(fn (string $label, string|int $value): array => ['value' => (string) $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * Keeps the items matching the chosen option. A value that is not an option is ignored.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, Module $module, string $value): void
    {
        if (! in_array($value, array_column($this->options($module), 'value'), true)) {
            return;
        }

        $field = $this->field($module);

        match (true) {
            $field instanceof Toggle => $query->where($this->name, $value === '1'),
            $field instanceof Select && $field->isMultiple() => $query->whereJsonContains($this->name, $this->optionKey($field, $value)),
            default => $query->where($this->name, $value),
        };
    }

    /**
     * @return array{name: string, label: string, options: array<int, array{value: string, label: string}>}
     */
    public function toSchema(Module $module): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label ?? $this->field($module)->getLabel(),
            'options' => $this->options($module),
        ];
    }

    /**
     * @throws LogicException When the filter is not made from a Select or Toggle field.
     */
    protected function field(Module $module): Select|Toggle
    {
        $field = $module->fields()[$this->name] ?? null;

        if (! $field instanceof Select && ! $field instanceof Toggle) {
            throw new LogicException(sprintf('Filter [%s] of module [%s] needs a Select or Toggle field of the same name.', $this->name, $module->name()));
        }

        return $field;
    }

    /**
     * Option keys keep their declared type in JSON (1, not "1").
     */
    protected function optionKey(Select $field, string $value): string|int
    {
        foreach (array_keys($field->resolvedOptions()) as $key) {
            if ((string) $key === $value) {
                return $key;
            }
        }

        return $value;
    }
}
