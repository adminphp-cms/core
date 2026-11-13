<?php

namespace Gmarineau\AdminPhp\Fields;

use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Illuminate\Database\Eloquent\Model;

/**
 * An on/off switch, stored as a boolean (never null).
 */
class Toggle extends Field
{
    use IsNotTranslatable;

    public function type(): string
    {
        return 'toggle';
    }

    /**
     * A toggle always has a value: "required" would only mean "must be on", so use accepted() for that.
     */
    public function validationRules(string $path, mixed $value): array
    {
        return [$path => ['nullable', 'boolean', ...$this->rules]];
    }

    public function accepted(): static
    {
        $this->rules = [...$this->rules, 'accepted'];

        return $this;
    }

    public function dehydrate(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function listingType(): string
    {
        return 'boolean';
    }

    public function listingValue(Model $model, string $locale): bool
    {
        return $this->valueFromModel($model);
    }

    public function valueFromModel(Model $model): bool
    {
        return (bool) $model->getAttribute($this->name);
    }
}
