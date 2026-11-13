<?php

namespace Gmarineau\AdminPhp\Fields;

use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Translatable\HasTranslations;

abstract class Field
{
    protected ?string $label = null;

    protected bool $isTranslatable = false;

    protected bool $isRequired = false;

    /**
     * @var array<int, mixed>
     */
    protected array $rules = [];

    final public function __construct(public readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Identifier of the field type, used by the admin front to pick its component.
     */
    abstract public function type(): string;

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function translatable(bool $isTranslatable = true): static
    {
        $this->isTranslatable = $isTranslatable;

        return $this;
    }

    public function required(bool $isRequired = true): static
    {
        $this->isRequired = $isRequired;

        return $this;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function isTranslatable(): bool
    {
        return $this->isTranslatable;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Validation rules for the value located at the given input path.
     * A required translatable field is only required in the default locale.
     *
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(string $path, mixed $value): array
    {
        if (! $this->isTranslatable) {
            return [$path => [$this->isRequired ? 'required' : 'nullable', ...$this->valueRules()]];
        }

        $adminPhp = app(AdminPhp::class);

        $rules = [$path => [$this->isRequired ? 'required' : 'nullable', 'array']];

        foreach ($adminPhp->locales() as $locale) {
            $isRequiredInLocale = $this->isRequired && $locale === $adminPhp->defaultLocale();

            $rules["{$path}.{$locale}"] = [$isRequiredInLocale ? 'required' : 'nullable', ...$this->valueRules()];
        }

        return $rules;
    }

    /**
     * Human names of the validated paths, so messages read "Titre (EN)" rather than "title.en".
     *
     * @return array<string, string>
     */
    public function validationAttributes(string $path, mixed $value): array
    {
        $attributes = [$path => $this->getLabel()];

        if ($this->isTranslatable) {
            foreach (app(AdminPhp::class)->locales() as $locale) {
                $attributes["{$path}.{$locale}"] = sprintf('%s (%s)', $this->getLabel(), strtoupper($locale));
            }
        }

        return $attributes;
    }

    /**
     * Normalizes a validated input value before it is stored.
     */
    public function dehydrate(mixed $value): mixed
    {
        if (! $this->isTranslatable) {
            return $value;
        }

        $translations = [];

        foreach (app(AdminPhp::class)->locales() as $locale) {
            if (filled($value[$locale] ?? null)) {
                $translations[$locale] = $value[$locale];
            }
        }

        return $translations;
    }

    /**
     * Writes the value on the model before it is saved.
     */
    public function fillModel(Model $model, mixed $value): void
    {
        $value = $this->dehydrate($value);

        if (! $this->isTranslatable) {
            $model->setAttribute($this->name, $value);

            return;
        }

        $this->ensureModelTranslates($model);

        $model->replaceTranslations($this->name, $value);
    }

    /**
     * Writes the value on an unsaved copy of the model shown in a preview. Nothing is
     * stored: fields that save outside the model's columns keep their value in memory.
     */
    public function fillPreview(Model $model, mixed $value): void
    {
        $this->fillModel($model, $value);
    }

    /**
     * Last chance to complete the model before it is saved. Called on every save for every
     * field of the form, even when the field was not submitted (partial update, new item),
     * once the submitted values are filled in.
     */
    public function beforeModelSave(Model $model): void {}

    /**
     * Persists what lives outside the model's own columns, once the model is saved.
     */
    public function afterModelSaved(Model $model, mixed $value): void {}

    /**
     * Reads the form value back from the model.
     */
    public function valueFromModel(Model $model): mixed
    {
        if (! $this->isTranslatable) {
            return $model->getAttribute($this->name);
        }

        $this->ensureModelTranslates($model);

        return $model->getTranslations($this->name);
    }

    /**
     * Ids of the media library assets the value uses, so an image is not deleted while
     * a content shows it. Fields holding other fields (blocks, repeaters) collect theirs.
     *
     * @return array<int, string>
     */
    public function assetIds(mixed $value): array
    {
        return [];
    }

    /**
     * How the value is shown in a listing column: text, boolean, date, datetime or image.
     * Null when the field cannot be shown as a column (rich text, blocks, lists…).
     */
    public function listingType(): ?string
    {
        return 'text';
    }

    /**
     * The value shown in a listing column, in the given locale (with a fallback on the others).
     */
    public function listingValue(Model $model, string $locale): mixed
    {
        $value = app(AdminPhp::class)->translate($this->valueFromModel($model), $locale);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Description of the field sent to the admin front.
     *
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type(),
            'label' => $this->getLabel(),
            'translatable' => $this->isTranslatable,
            'required' => $this->isRequired,
            ...$this->schemaAttributes(),
        ];
    }

    /**
     * Type-specific attributes added to the schema.
     *
     * @return array<string, mixed>
     */
    protected function schemaAttributes(): array
    {
        return [];
    }

    /**
     * Rules applied to a single value (one locale for translatable fields).
     *
     * @return array<int, mixed>
     */
    protected function valueRules(): array
    {
        return $this->rules;
    }

    protected function ensureModelTranslates(Model $model): void
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($model), true)
            || ! $model->isTranslatableAttribute($this->name)) {
            throw new LogicException(sprintf(
                'Field [%s] is translatable: add [%s] to the $translatable attributes of [%s] (HasTranslations).',
                $this->name,
                $this->name,
                $model::class,
            ));
        }
    }
}
