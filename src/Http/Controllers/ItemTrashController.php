<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\DuplicateItem;
use Gmarineau\AdminPhp\Actions\RunBulkAction;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ItemTrashController
{
    use ResolvesModuleItems;

    /**
     * Moves the item to the trash, from where it can be restored.
     */
    public function destroy(string $module, string $id): Response
    {
        $moduleDefinition = $this->findModule($module);

        abort_unless($moduleDefinition->isTrashable(), 404);

        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $item->delete();

        return response()->noContent();
    }

    public function duplicate(DuplicateItem $duplicateItem, string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        $copy = $duplicateItem->handle($moduleDefinition, $this->findItem($moduleDefinition, $id));

        return $this->itemResponse($moduleDefinition, $copy, 201);
    }

    /**
     * Applies a listing action (publish, unpublish, trash, restore, delete) to the selected items.
     */
    public function bulk(Request $request, RunBulkAction $runBulkAction, string $module): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(RunBulkAction::ACTIONS)],
            'ids' => ['required', 'array', 'list', 'max:100'],
            'ids.*' => ['string'],
        ]);

        try {
            $result = $runBulkAction->handle($moduleDefinition, $validated['action'], $validated['ids']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()->json(['data' => $result]);
    }
}
