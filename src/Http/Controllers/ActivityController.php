<?php

namespace AdminPhp\Http\Controllers;

use AdminPhp\AdminPhp;
use AdminPhp\Enums\RevisionStatus;
use AdminPhp\Models\Revision;
use Illuminate\Http\JsonResponse;

/**
 * Latest saved drafts and publications, across every module, for the dashboard.
 */
class ActivityController
{
    protected const LIMIT = 8;

    public function __invoke(AdminPhp $adminPhp): JsonResponse
    {
        $moduleNames = array_keys($adminPhp->modules());
        $locale = $adminPhp->defaultLocale();

        $revisions = Revision::query()
            ->whereIn('revisionable_type', $moduleNames)
            ->with(['user:id,name', 'revisionable'])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (Revision $revision): bool => $revision->revisionable !== null);

        return response()->json([
            'data' => $revisions->map(function (Revision $revision) use ($adminPhp, $locale): array {
                $module = $adminPhp->module($revision->revisionable_type);

                return [
                    'id' => $revision->id,
                    'action' => $revision->status === RevisionStatus::Draft ? 'saved' : 'published',
                    'module' => $module->name(),
                    'moduleTitle' => $module->title(),
                    'icon' => $module->icon(),
                    'itemId' => $revision->revisionable_id,
                    'itemTitle' => $module->itemTitle($revision->revisionable, $locale),
                    'userName' => $revision->user?->name,
                    'at' => $revision->updated_at->toIso8601String(),
                ];
            })->values()->all(),
        ]);
    }
}
