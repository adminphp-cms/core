<?php

namespace Gmarineau\AdminPhp\Modules;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Concerns\HasNesting;
use Gmarineau\AdminPhp\Concerns\HasPosition;
use Gmarineau\AdminPhp\Concerns\HasPublication;
use Gmarineau\AdminPhp\Fields\Field;
use Gmarineau\AdminPhp\Forms\HasFields;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;

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
     * Initial order of the listing: a column name, with a leading "-" for descending order.
     * Modules ordered by hand (HasPosition) are listed in their order instead.
     */
    protected string $defaultSort = '-updated_at';

    /**
     * Blade view of the site showing an item, used to preview unsaved changes in the admin.
     * It receives the item as `$item` and under the module name (`$article`…). Null: no preview.
     */
    protected ?string $previewView = null;

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
     * How many levels items can be nested under others (0: a flat list, 1: items and
     * sub-items…). Needs HasNesting and HasPosition on the model.
     */
    protected int $nestingDepth = 0;

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
     * Columns of the admin listing: the title, the publication status and the last change by default.
     *
     * @return array<int, Column>
     */
    public function columns(): array
    {
        return [
            Column::make($this->titleField)->sortable(),
            ...($this->isPublishable() ? [Column::status()] : []),
            Column::make('updated_at')->label('Modifié')->sortable(),
        ];
    }

    /**
     * Filters of the admin listing, made from Select and Toggle fields.
     *
     * @return array<int, Filter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @return array<string, Column>
     */
    public function listingColumns(): array
    {
        $columns = [];

        foreach ($this->columns() as $column) {
            $columns[$column->name] = $column;
        }

        return $columns;
    }

    /**
     * @return array<string, Filter>
     */
    public function listingFilters(): array
    {
        $filters = [];

        foreach ($this->filters() as $filter) {
            $filters[$filter->name] = $filter;
        }

        return $filters;
    }

    public function defaultSort(): string
    {
        return $this->defaultSort;
    }

    public function titleField(): string
    {
        return $this->titleField;
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

    public function isPreviewable(): bool
    {
        return $this->previewView !== null;
    }

    /**
     * Renders the preview of an item built from the admin form, nothing saved.
     * Override it to pass other variables or to render something else than a view.
     */
    public function preview(Model $item): Renderable|string
    {
        return view($this->previewView ?? throw new LogicException(sprintf('Module [%s] has no $previewView.', $this->name())), $this->previewData($item));
    }

    /**
     * Variables of the preview view.
     *
     * @return array<string, mixed>
     */
    protected function previewData(Model $item): array
    {
        return ['item' => $item, Str::camel($this->name()) => $item];
    }

    /**
     * Whether deleted items go to a trash, from where they can be restored (SoftDeletes).
     */
    public function isTrashable(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    /**
     * Whether editors order the items by hand (HasPosition).
     */
    public function isSortable(): bool
    {
        return in_array(HasPosition::class, class_uses_recursive($this->model), true);
    }

    /**
     * @throws LogicException When the model cannot be nested.
     */
    public function nestingDepth(): int
    {
        if ($this->nestingDepth > 0 && (! $this->isSortable() || ! in_array(HasNesting::class, class_uses_recursive($this->model), true))) {
            throw new LogicException(sprintf('Module [%s] nests its items: add HasPosition and HasNesting to [%s].', $this->name(), $this->model));
        }

        return $this->nestingDepth;
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
            'sortable' => $this->isSortable(),
            'trashable' => $this->isTrashable(),
            'previewable' => $this->isPreviewable(),
            'nestingDepth' => $this->nestingDepth(),
            'listing' => [
                'columns' => array_values(array_map(fn (Column $column): array => $column->toSchema($this), $this->listingColumns())),
                'filters' => array_values(array_map(fn (Filter $filter): array => $filter->toSchema($this), $this->listingFilters())),
                'defaultSort' => $this->defaultSort,
            ],
            'fields' => array_values(array_map(fn (Field $field): array => $field->toSchema(), $this->fields())),
        ];
    }
}
