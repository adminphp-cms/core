<?php

namespace AdminPhp\Http\Controllers;

use AdminPhp\Actions\ListModuleItems;
use AdminPhp\Actions\SaveDraft;
use AdminPhp\Actions\SaveModuleContent;
use AdminPhp\AdminPhp;
use AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleItemController
{
    use ResolvesModuleItems;

    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * Items of a module for the admin listing. Query: `search`, `filters[name]=value`,
     * `view` (all, published, unpublished, pending), `sort` (a column, "-" for descending) and `page`.
     */
    public function index(Request $request, ListModuleItems $listModuleItems, string $module): JsonResponse
    {
        $filters = $request->query('filters');
        $sort = $request->query('sort');

        return response()->json($listModuleItems->handle($this->findModule($module), [
            'search' => (string) $request->query('search', ''),
            'filters' => is_array($filters) ? $filters : [],
            'view' => (string) $request->query('view', 'all'),
            'sort' => is_string($sort) && $sort !== '' ? $sort : null,
            'page' => (int) $request->query('page', 1),
        ]));
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

        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $item = $saveDraft->handle($moduleDefinition, $item, $request->all());

        return $this->itemResponse($moduleDefinition, $item);
    }
}
