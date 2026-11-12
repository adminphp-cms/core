<?php

namespace Gmarineau\AdminPhp\Modules;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Concerns\HasPublication;
use Gmarineau\AdminPhp\Fields\Field;
use Gmarineau\AdminPhp\Forms\HasFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class Module
{
    use HasFields;

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
     * Restricts a query of the module's items to the ones whose title contains the term,
     * in any locale. Override it to search other columns.
     *
     * @param  Builder<Model>  $query
     */
    public function search(Builder $query, string $term): void
    {
        $field = $this->fields()[$this->titleField] ?? null;
        $pattern = '%'.addcslashes($term, '%_\\').'%';

        if ($field === null) {
            $query->whereKey($term);

            return;
        }

        if (! $field->isTranslatable()) {
            $query->whereLike($field->name, $pattern, caseSensitive: false);

            return;
        }

        $query->where(function (Builder $query) use ($field, $pattern): void {
            foreach (app(AdminPhp::class)->locales() as $locale) {
                $query->orWhereLike("{$field->name}->{$locale}", $pattern, caseSensitive: false);
            }
        });
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
