<?php

namespace AdminPhp\Http\Controllers;

use AdminPhp\AdminPhp;
use AdminPhp\Enums\PublicationStatus;
use AdminPhp\Modules\Module;
use Illuminate\Http\JsonResponse;

class ModuleController
{
    /**
     * Modules shown in the navigation and on the dashboard, with their counts.
     * Publishable modules also tell how many items are online and how many wait for publication.
     */
    public function index(AdminPhp $adminPhp): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map(fn (Module $module): array => [
                'name' => $module->name(),
                'title' => $module->title(),
                'description' => $module->description(),
                'icon' => $module->icon(),
                'count' => $module->modelClass()::query()->count(),
                'counts' => $module->isPublishable() ? $this->publicationCounts($module) : null,
            ], $adminPhp->modules())),
        ]);
    }

    /**
     * Form schema of a module.
     */
    public function show(AdminPhp $adminPhp, string $module): JsonResponse
    {
        abort_unless($adminPhp->hasModule($module), 404);

        return response()->json(['data' => $adminPhp->module($module)->toSchema()]);
    }

    /**
     * @return array{published: int, unpublished: int, pendingChanges: int}
     */
    protected function publicationCounts(Module $module): array
    {
        $query = $module->modelClass()::query();

        return [
            'published' => (clone $query)->where('status', PublicationStatus::Published->value)->count(),
            'unpublished' => (clone $query)->where('status', PublicationStatus::Draft->value)->count(),
            'pendingChanges' => (clone $query)->whereHas('draftRevision')->count(),
        ];
    }
}
