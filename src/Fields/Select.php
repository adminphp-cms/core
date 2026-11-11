<?php

namespace Gmarineau\AdminPhp\Fields;

use BackedEnum;
use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * A choice among fixed options, given as `value => label` or as a backed enum
 * (labels come from a `label()` method on the enum when it has one).
 * A multiple select stores a list: cast its column to `array` on the model.
 */
class Select extends Field
{
    use IsNotTranslatable;

    /**
     * @var array<string|int, string>
     */
    protected array $options = [];

    protected bool $isMultiple = false;

    public function type(): string
    {
        return 'select';
    }

    /**
     * @param  array<string|int, string>|class-string<BackedEnum>  $options
     */
    public function options(array|string $options): static
    {
        if (is_string($options)) {
            if (! is_subclass_of($options, BackedEnum::class)) {
                throw new InvalidArgumentException("Select options must be an array or a backed enum class, [{$options}] given.");
            }

            $options = collect($options::cases())->mapWithKeys(fn (BackedEnum $case): array => [
                $case->value => method_exists($case, 'label') ? $case->label() : Str::headline($case->name),
            ])->all();
        }

        $this->options = $options;

        return $this;
    }

    public function multiple(bool $isMultiple = true): static
    {
        $this->isMultiple = $isMultiple;

        return $this;
    }

    public function validationRules(string $path, mixed $value): array
    {
        $optionRule = Rule::in(array_map(strval(...), array_keys($this->options)));

        if (! $this->isMultiple) {
            return [$path => [$this->isRequired ? 'required' : 'nullable', $optionRule, ...$this->rules]];
        }

        return [
            $path => [$this->isRequired ? 'required' : 'nullable', 'array', 'list', ...$this->rules],
            "{$path}.*" => ['distinct', $optionRule],
        ];
    }

    /**
     * Gives back the option keys with their declared type (form inputs send strings).
     */
    public function dehydrate(mixed $value): mixed
    {
        if ($this->isMultiple) {
            return array_values(array_map($this->optionKey(...), is_array($value) ? $value : []));
        }

        return $value === null || $value === '' ? null : $this->optionKey($value);
    }

    public function valueFromModel(Model $model): mixed
    {
        $value = $model->getAttribute($this->name);

        if ($this->isMultiple) {
            return array_map($this->scalarValue(...), is_array($value) ? $value : []);
        }

        return $this->scalarValue($value);
    }

    protected function schemaAttributes(): array
    {
        return [
            'multiple' => $this->isMultiple,
            'options' => collect($this->options)
                ->map(fn (string $label, string|int $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    protected function optionKey(mixed $value): string|int
    {
        foreach (array_keys($this->options) as $key) {
            if ((string) $key === (string) $value) {
                return $key;
            }
        }

        return $value;
    }

    protected function scalarValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
