<?php

namespace AdminPhp\Actions;

use AdminPhp\Modules\Module;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Saves the order (and nesting) chosen by drag and drop in the admin listing. The items come
 * in their new order, depth first: a parent always comes before its children. Reordering does
 * not touch the content itself, so it neither goes through drafts nor changes `updated_at`.
 */
class ReorderItems
{
    /**
     * @param  array<int, mixed>  $items  [['id' => …, 'parentId' => null|…], …]
     *
     * @throws ValidationException
     */
    public function handle(Module $module, array $items): void
    {
        if (! $module->isSortable()) {
            throw new LogicException(sprintf('Module [%s] is not sortable: add HasPosition to its model.', $module->name()));
        }

        $maxDepth = $module->nestingDepth();

        $validated = Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'list'],
            'items.*' => ['array'],
            'items.*.id' => ['required', 'string', 'distinct'],
            'items.*.parentId' => [$maxDepth > 0 ? 'nullable' : 'prohibited', 'string'],
        ])->validate()['items'];

        $modelClass = $module->modelClass();
        $ids = array_column($validated, 'id');

        if ($modelClass::query()->whereKey($ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'Un des éléments n’existe plus : rechargez la page.']);
        }

        $depths = [];
        $nextPositions = [];
        $updates = [];

        foreach ($validated as $item) {
            $parentId = $item['parentId'] ?? null;

            if ($parentId !== null && ! isset($depths[$parentId])) {
                throw ValidationException::withMessages(['items' => 'Un élément doit suivre son parent.']);
            }

            $depth = $parentId === null ? 0 : $depths[$parentId] + 1;

            if ($depth > $maxDepth) {
                throw ValidationException::withMessages(['items' => sprintf('Les éléments ne peuvent pas être imbriqués sur plus de %d niveaux.', $maxDepth + 1)]);
            }

            $depths[$item['id']] = $depth;
            $position = $nextPositions[$parentId ?? ''] = ($nextPositions[$parentId ?? ''] ?? 0) + 1;

            $updates[$item['id']] = ['position' => $position, ...($maxDepth > 0 ? ['parent_id' => $parentId] : [])];
        }

        $model = new $modelClass;

        $model->getConnection()->transaction(function () use ($modelClass, $updates): void {
            foreach ($updates as $id => $columns) {
                $modelClass::query()->whereKey($id)->toBase()->update($columns);
            }
        });
    }
}
