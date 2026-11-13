<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Models\Asset;
use Gmarineau\AdminPhp\Modules\Column;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The admin listing of a module: search, filters, publication view, sort and pages.
 * A module ordered by hand is listed whole, in its order, while nothing narrows or
 * reorders it, so it can be dragged around as a tree.
 */
class ListModuleItems
{
    public const PER_PAGE = 30;

    /** Publication views of a publishable module, as tabs above the listing. */
    public const VIEWS = ['all', 'published', 'unpublished', 'pending', 'trash'];

    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * @param  array{search?: string, filters?: array<string, mixed>, view?: string, sort?: string|null, page?: int}  $options
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function handle(Module $module, array $options = []): array
    {
        $search = trim($options['search'] ?? '');
        $isPublishable = $module->isPublishable();
        $isTrashable = $module->isTrashable();
        $view = $this->view($options['view'] ?? 'all', $isPublishable, $isTrashable);
        $requestedSort = $options['sort'] ?? null;

        $query = $module->modelClass()::query()
            ->when($isPublishable, fn (Builder $query) => $query->withExists('draftRevision as has_draft'))
            ->when($search !== '', fn (Builder $query) => $module->search($query, $search));

        $activeFilters = $this->applyFilters($query, $module, $options['filters'] ?? []);
        $counts = $isPublishable || $isTrashable ? $this->viewCounts(clone $query, $isPublishable, $isTrashable) : null;

        $this->applyView($query, $view);

        $isTree = $module->isSortable() && $search === '' && $activeFilters === [] && $view === 'all' && $requestedSort === null;

        if ($isTree) {
            $items = $query->orderBy('position')->get();
            $paginator = new LengthAwarePaginator($items, $items->count(), max($items->count(), 1));
            $sort = null;
        } else {
            $sort = $this->applySort($query, $module, $requestedSort);
            $paginator = $query->paginate(self::PER_PAGE, page: max(1, (int) ($options['page'] ?? 1)));
        }

        return [
            'data' => $this->rows($module, $paginator),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'sort' => $sort,
                'view' => $counts !== null ? $view : null,
                'filters' => (object) $activeFilters,
                'counts' => $counts,
                'isTree' => $isTree,
            ],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $values
     * @return array<string, string> The filters applied, by name.
     */
    protected function applyFilters(Builder $query, Module $module, array $values): array
    {
        $applied = [];

        foreach ($module->listingFilters() as $name => $filter) {
            $value = $values[$name] ?? null;

            if (is_scalar($value) && (string) $value !== '' && in_array((string) $value, array_column($filter->options($module), 'value'), true)) {
                $filter->apply($query, $module, (string) $value);
                $applied[$name] = (string) $value;
            }
        }

        return $applied;
    }

    /**
     * The requested view when the module has it, else all items.
     */
    protected function view(string $requested, bool $isPublishable, bool $isTrashable): string
    {
        $available = ['all', ...($isPublishable ? ['published', 'unpublished', 'pending'] : []), ...($isTrashable ? ['trash'] : [])];

        return in_array($requested, $available, true) ? $requested : 'all';
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applyView(Builder $query, string $view): void
    {
        match ($view) {
            'trash' => $query->onlyTrashed(),
            'published' => $query->where('status', PublicationStatus::Published->value),
            'unpublished' => $query->where('status', PublicationStatus::Draft->value),
            'pending' => $query->whereHas('draftRevision'),
            default => null,
        };
    }

    /**
     * How many items each view of the module holds, with the search and filters applied.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    protected function viewCounts(Builder $query, bool $isPublishable, bool $isTrashable): array
    {
        $query->withoutEagerLoads();
        $query->getQuery()->columns = null;

        return [
            'all' => (clone $query)->count(),
            ...($isPublishable ? [
                'published' => (clone $query)->where('status', PublicationStatus::Published->value)->count(),
                'unpublished' => (clone $query)->where('status', PublicationStatus::Draft->value)->count(),
                'pending' => (clone $query)->whereHas('draftRevision')->count(),
            ] : []),
            ...($isTrashable ? ['trash' => (clone $query)->onlyTrashed()->count()] : []),
        ];
    }

    /**
     * Sorts by the requested sortable column, else by the module's default sort.
     *
     * @param  Builder<Model>  $query
     * @return string The sort applied, e.g. "-updated_at".
     */
    protected function applySort(Builder $query, Module $module, ?string $requestedSort): string
    {
        $columns = $module->listingColumns();
        $sort = $requestedSort;
        $name = ltrim((string) $sort, '-');

        if ($sort === null || ! ($columns[$name] ?? null)?->isSortable()) {
            $sort = $module->defaultSort();
            $name = ltrim($sort, '-');
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = $columns[$name] ?? Column::make($name);

        $column->applySort($query, $module, $direction);
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);

        return $sort;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(Module $module, LengthAwarePaginatorContract $paginator): array
    {
        $locale = $this->adminPhp->defaultLocale();
        $columns = $module->listingColumns();
        $isPublishable = $module->isPublishable();
        $isNested = $module->nestingDepth() > 0;
        $assets = $this->assets($module, $columns, $paginator->items(), $locale);
        $locks = app(ManageEditLock::class)->activeLocks($paginator->items());
        $userId = $this->adminPhp->user()?->getKey();

        return array_map(fn (Model $item): array => [
            'id' => $item->getKey(),
            'title' => $module->itemTitle($item, $locale),
            'cells' => (object) array_map(fn (Column $column): mixed => $column->value($item, $module, $locale, $assets), $columns),
            'updatedAt' => $item->getAttribute('updated_at')?->toIso8601String(),
            'visibility' => $isPublishable ? $item->visibility()->value : null,
            'hasDraft' => $isPublishable && $item->getAttribute('has_draft'),
            'parentId' => $isNested ? $item->getAttribute('parent_id') : null,
            'lockedBy' => ($lock = $locks[(string) $item->getKey()] ?? null) !== null && $lock->user_id !== $userId ? $lock->user?->name : null,
        ], $paginator->items());
    }

    /**
     * Assets shown by the image columns of the page, loaded in one query.
     *
     * @param  array<string, Column>  $columns
     * @param  array<int, Model>  $items
     * @return array<string, Asset>
     */
    protected function assets(Module $module, array $columns, array $items, string $locale): array
    {
        $fields = $module->fields();
        $ids = [];

        foreach ($columns as $name => $column) {
            $field = $fields[$name] ?? null;

            if ($column->isStatus() || $field?->listingType() !== 'image') {
                continue;
            }

            foreach ($items as $item) {
                $ids[] = $field->listingValue($item, $locale);
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : Asset::query()->whereKey($ids)->get()->keyBy('id')->all();
    }
}
