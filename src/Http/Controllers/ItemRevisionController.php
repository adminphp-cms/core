<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\RestoreRevision;
use Gmarineau\AdminPhp\Enums\RevisionStatus;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Gmarineau\AdminPhp\Models\Revision;
use Illuminate\Http\JsonResponse;

class ItemRevisionController
{
    use ResolvesModuleItems;

    /**
     * Published versions of the item, most recent first.
     */
    public function index(string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findPublishableModule($module);
        $item = $this->findItem($moduleDefinition, $id);

        $revisions = $item->revisions()
            ->where('status', RevisionStatus::Published)
            ->with('user:id,name')
            ->limit(50)
            ->get(['id', 'status', 'user_id', 'created_at']);

        return response()->json([
            'data' => $revisions->map(fn (Revision $revision): array => [
                'id' => $revision->id,
                'createdAt' => $revision->created_at->toIso8601String(),
                'userName' => $revision->user?->name,
            ])->all(),
        ]);
    }

    public function restore(RestoreRevision $restoreRevision, string $module, string $id, string $revision): JsonResponse
    {
        $moduleDefinition = $this->findPublishableModule($module);
        $item = $this->findItem($moduleDefinition, $id);
        $this->ensureNotLockedByOther($item);

        $revisionToRestore = $item->revisions()
            ->where('status', RevisionStatus::Published)
            ->findOrFail($revision);

        $restoreRevision->handle($moduleDefinition, $item, $revisionToRestore);

        return $this->itemResponse($moduleDefinition, $item);
    }
}
