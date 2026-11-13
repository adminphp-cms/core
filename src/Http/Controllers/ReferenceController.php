<?php

namespace AdminPhp\Http\Controllers;

use AdminPhp\AdminPhp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController
{
    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * Names the contents chosen in Browser fields (?items[]=article:01J…), in the given order,
     * with one query per module. References to a missing content or module are left out.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array', 'max:200'],
            'items.*' => ['string', 'max:200'],
        ]);

        $references = [];

        foreach ($validated['items'] ?? [] as $item) {
            [$type, $id] = array_pad(explode(':', $item, 2), 2, '');

            if ($this->adminPhp->hasModule($type) && $id !== '') {
                $references[] = [$type, $id];
            }
        }

        $models = [];

        foreach (collect($references)->groupBy(0) as $type => $typeReferences) {
            $modelClass = $this->adminPhp->module($type)->modelClass();

            foreach ($modelClass::query()->whereKey($typeReferences->pluck(1)->all())->get() as $model) {
                $models["{$type}:{$model->getKey()}"] = $model;
            }
        }

        $locale = $this->adminPhp->defaultLocale();
        $data = [];

        foreach ($references as [$type, $id]) {
            $model = $models["{$type}:{$id}"] ?? null;

            if ($model === null) {
                continue;
            }

            $module = $this->adminPhp->module($type);

            $data[] = [
                'type' => $type,
                'id' => (string) $model->getKey(),
                'title' => $module->itemTitle($model, $locale),
                'moduleTitle' => $module->title(),
                'icon' => $module->icon(),
                'visibility' => $module->isPublishable() ? $model->visibility()->value : null,
            ];
        }

        return response()->json(['data' => $data]);
    }
}
