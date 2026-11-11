<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\SaveDraft;
use Gmarineau\AdminPhp\Actions\SaveModuleContent;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleItemController
{
    use ResolvesModuleItems;

    public function __construct(protected AdminPhp $adminPhp) {}

    public function index(string $module): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);
        $locale = $this->adminPhp->defaultLocale();
        $isPublishable = $moduleDefinition->isPublishable();

        $items = $moduleDefinition->modelClass()::query()
            ->when($isPublishable, fn ($query) => $query->withExists('draftRevision as has_draft'))
            ->latest('updated_at')
            ->paginate(30);

        return response()->json([
            'data' => $items->getCollection()->map(fn (Model $item): array => [
                'id' => $item->getKey(),
                'title' => $moduleDefinition->itemTitle($item, $locale),
                'updatedAt' => $item->getAttribute('updated_at')?->toIso8601String(),
                'visibility' => $isPublishable ? $item->visibility()->value : null,
                'hasDraft' => $isPublishable && $item->getAttribute('has_draft'),
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
