<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\PublishItem;
use Gmarineau\AdminPhp\Actions\UnpublishItem;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemPublicationController
{
    use ResolvesModuleItems;

    /**
     * Publishes the item with its draft, the unsaved `values` and the publication window.
     */
    public function publish(Request $request, PublishItem $publishItem, string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findPublishableModule($module);
        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $validated = $request->validate([
            'values' => ['nullable', 'array'],
            'publishStartAt' => ['nullable', 'date'],
            'publishEndAt' => ['nullable', 'date', $request->filled('publishStartAt') ? 'after:publishStartAt' : 'after:now'],
        ]);

        $publishItem->handle(
            $moduleDefinition,
            $item,
            $validated['values'] ?? [],
            $request->date('publishStartAt'),
            $request->date('publishEndAt'),
        );

        return $this->itemResponse($moduleDefinition, $item);
    }

    public function unpublish(UnpublishItem $unpublishItem, string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findPublishableModule($module);

        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $item = $unpublishItem->handle($moduleDefinition, $item);

        return $this->itemResponse($moduleDefinition, $item);
    }

    /**
     * Drops the pending changes: the item goes back to its published values.
     */
    public function discardDraft(string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findPublishableModule($module);
        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $item->draftRevision()->delete();

        return $this->itemResponse($moduleDefinition, $item);
    }
}
