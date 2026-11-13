<?php

namespace Gmarineau\AdminPhp\Http\Controllers\Concerns;

use Gmarineau\AdminPhp\Actions\GetFormData;
use Gmarineau\AdminPhp\Actions\ManageEditLock;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait ResolvesModuleItems
{
    protected function findModule(string $name): Module
    {
        $adminPhp = app(AdminPhp::class);

        abort_unless($adminPhp->hasModule($name), 404);

        return $adminPhp->module($name);
    }

    protected function findPublishableModule(string $name): Module
    {
        $module = $this->findModule($name);

        abort_unless($module->isPublishable(), 404);

        return $module;
    }

    protected function findItem(Module $module, string $id): Model
    {
        return $module->modelClass()::query()->findOrFail($id);
    }

    /**
     * Stops a change to an item someone else is editing (423 Locked): their editor holds the lock.
     */
    protected function ensureNotLockedByOther(Model $item): void
    {
        $lock = app(ManageEditLock::class)->heldByOther($item, app(AdminPhp::class)->user());

        if ($lock !== null) {
            abort(423, sprintf('%s modifie ce contenu en ce moment : vos changements ne peuvent pas être enregistrés.', $lock->user?->name ?? 'Quelqu’un'));
        }
    }

    /**
     * The item as edited in the admin: its pending draft when there is one, else its live values.
     */
    protected function itemResponse(Module $module, Model $item, int $status = 200): JsonResponse
    {
        $item = $item->fresh();
        $values = app(GetFormData::class)->handle($module, $item);
        $publication = null;

        if ($module->isPublishable()) {
            $draft = $item->draftRevision()->with('user:id,name')->first();

            $values = [...$values, ...($draft->payload ?? [])];

            $publication = [
                'status' => $item->status->value,
                'visibility' => $item->visibility()->value,
                'publishedAt' => $item->published_at?->toIso8601String(),
                'publishStartAt' => $item->publish_start_at?->toIso8601String(),
                'publishEndAt' => $item->publish_end_at?->toIso8601String(),
                'hasDraft' => $draft !== null,
                'draftUpdatedAt' => $draft?->updated_at->toIso8601String(),
                'draftUpdatedBy' => $draft?->user?->name,
            ];
        }

        return response()->json([
            'data' => [
                'id' => $item->getKey(),
                'title' => $module->itemTitle($item, app(AdminPhp::class)->defaultLocale()),
                'values' => $values,
                'publication' => $publication,
            ],
        ], $status);
    }
}
