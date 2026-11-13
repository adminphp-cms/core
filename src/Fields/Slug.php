<?php

namespace AdminPhp\Fields;

use AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * The address of a content, made from another field (the title by default).
 *
 * Left empty, it is generated on save from that field; typed by hand, it is
 * normalized (lowercase, no accents, dashes). It never follows later changes
 * of the title, so published addresses stay stable. Unique by default, trashed
 * contents included: a taken slug gets a "-2", "-3"… suffix.
 */
class Slug extends Text
{
    protected string $sourceField = 'title';

    protected string $prefix = '/';

    protected bool $isUnique = true;

    public function type(): string
    {
        return 'slug';
    }

    /**
     * Field the slug is made from.
     */
    public function from(string $sourceField): static
    {
        $this->sourceField = $sourceField;

        return $this;
    }

    /**
     * Part of the address shown before the slug in the admin, e.g. "/blog/".
     */
    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function unique(bool $isUnique = true): static
    {
        $this->isUnique = $isUnique;

        return $this;
    }

    public function getSourceField(): string
    {
        return $this->sourceField;
    }

    /**
     * An empty slug is never required: it is generated on save.
     */
    public function validationRules(string $path, mixed $value): array
    {
        $isRequired = $this->isRequired;
        $this->isRequired = false;

        try {
            return parent::validationRules($path, $value);
        } finally {
            $this->isRequired = $isRequired;
        }
    }

    public function dehydrate(mixed $value): mixed
    {
        $normalize = fn (mixed $text): ?string => is_string($text) && $this->slugify($text) !== '' ? $this->slugify($text) : null;

        if (! $this->isTranslatable) {
            return $normalize($value);
        }

        $locales = array_flip(app(AdminPhp::class)->locales());

        return array_filter(array_map($normalize, array_intersect_key(is_array($value) ? $value : [], $locales)));
    }

    public function beforeModelSave(Model $model): void
    {
        if (! $this->isTranslatable) {
            $slug = $this->completeSlug($model, $model->getAttribute($this->name), $this->sourceText($model, null));
            $model->setAttribute($this->name, $slug);

            return;
        }

        $this->ensureModelTranslates($model);

        $slugs = [];

        foreach (app(AdminPhp::class)->locales() as $locale) {
            $slug = $this->completeSlug($model, $model->getTranslation($this->name, $locale, false) ?: null, $this->sourceText($model, $locale), $locale);

            if ($slug !== null) {
                $slugs[$locale] = $slug;
            }
        }

        if ($slugs !== $model->getTranslations($this->name)) {
            $model->replaceTranslations($this->name, $slugs);
        }
    }

    /**
     * Same rules as Laravel's Str::slug, which the admin front mirrors.
     */
    public function slugify(string $text): string
    {
        return Str::limit(Str::slug($text), $this->maxLength, '');
    }

    protected function schemaAttributes(): array
    {
        return [
            ...parent::schemaAttributes(),
            'from' => $this->sourceField,
            'prefix' => $this->prefix,
        ];
    }

    /**
     * Generates a missing slug, then makes a new or changed one unique.
     */
    protected function completeSlug(Model $model, ?string $slug, ?string $sourceText, ?string $locale = null): ?string
    {
        $isGenerated = $slug === null || $slug === '';

        if ($isGenerated) {
            $slug = $sourceText === null ? '' : $this->slugify($sourceText);
        }

        if ($slug === '') {
            return null;
        }

        $isChanged = $isGenerated || ! $model->exists || $model->isDirty($this->name);

        return $this->isUnique && $isChanged ? $this->uniqueSlug($model, $slug, $locale) : $slug;
    }

    /**
     * Text of the source field: for a translated slug, in that exact locale (no title, no slug);
     * for a single slug, in the default locale, else in any filled one.
     */
    protected function sourceText(Model $model, ?string $locale): ?string
    {
        $value = $model->getAttribute($this->sourceField);

        if (in_array(HasTranslations::class, class_uses_recursive($model), true)
            && $model->isTranslatableAttribute($this->sourceField)) {
            $value = $model->getTranslations($this->sourceField);
        }

        if (! is_array($value)) {
            return is_string($value) && $value !== '' ? $value : null;
        }

        if ($locale !== null) {
            return is_string($value[$locale] ?? null) && $value[$locale] !== '' ? $value[$locale] : null;
        }

        foreach (array_unique([app(AdminPhp::class)->defaultLocale(), ...array_keys($value)]) as $candidate) {
            if (is_string($value[$candidate] ?? null) && $value[$candidate] !== '') {
                return $value[$candidate];
            }
        }

        return null;
    }

    protected function uniqueSlug(Model $model, string $slug, ?string $locale): string
    {
        $column = $locale === null ? $this->name : "{$this->name}->{$locale}";
        $candidate = $slug;

        for ($suffix = 2; $this->isTaken($model, $column, $candidate); $suffix++) {
            $candidate = Str::limit($slug, $this->maxLength - strlen("-{$suffix}"), '')."-{$suffix}";
        }

        return $candidate;
    }

    protected function isTaken(Model $model, string $column, string $slug): bool
    {
        $query = $model->newQuery();

        // A trashed content keeps its slug: it gets it back when restored.
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $query->withTrashed();
        }

        return $query
            ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
            ->where($column, $slug)
            ->exists();
    }
}
