<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\SaveDraft;
use Gmarineau\AdminPhp\Actions\SaveModuleContent;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ModuleItemController
{
    use ResolvesModuleItems;

    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * Items of a module, last updated first. `?search=` filters them by title.
     */
    public function index(Request $request, string $module): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);
        $search = trim((string) $request->query('search', ''));
        $locale = $this->adminPhp->defaultLocale();
        $isPublishable = $moduleDefinition->isPublishable();

        $query = $moduleDefinition->modelClass()::query()
            ->when($isPublishable, fn ($query) => $query->withExists('draftRevision as has_draft'))
            ->when($search !== '', fn ($query) => $moduleDefinition->search($query, $search));

        // Items ordered by hand are listed all at once, in their order, to be dragged around.
        $items = $moduleDefinition->isSortable()
            ? new LengthAwarePaginator($collection = $query->orderBy('position')->get(), $collection->count(), max($collection->count(), 1))
            : $query->latest('updated_at')->paginate(30);

        $isNested = $moduleDefinition->nestingDepth() > 0;

        return response()->json([
            'data' => $items->getCollection()->map(fn (Model $item): array => [
                'id' => $item->getKey(),
                'title' => $moduleDefinition->itemTitle($item, $locale),
                'updatedAt' => $item->getAttribute('updated_at')?->toIso8601String(),
                'visibility' => $isPublishable ? $item->visibility()->value : null,
                'hasDraft' => $isPublishable && $item->getAttribute('has_draft'),
                'parentId' => $isNested ? $item->getAttribute('parent_id') : null,
            ])->all(),
            'meta' => [
                'currentPage' => $items->currentPage(),
                'lastPage' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        return $this->itemResponse($moduleDefinition, $this->findItem($moduleDefinition, $id));
    }

    public function store(Request $request, SaveModuleContent $saveModuleContent, string $module): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        $item = $saveModuleContent->handle($moduleDefinition, $request->all());

        return $this->itemResponse($moduleDefinition, $item, 201);
    }

    /**
     * Saves the changes: in place, or into the draft of a published item.
     */
    public function update(Request $request, SaveDraft $saveDraft, string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        $item = $saveDraft->handle($moduleDefinition, $this->findItem($moduleDefinition, $id), $request->all());

        return $this->itemResponse($moduleDefinition, $item);
    }
}
