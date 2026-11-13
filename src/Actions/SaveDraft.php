<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Enums\RevisionStatus;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Saves the changes made in the admin without touching what the site shows.
 *
 * - Content that is not published (or a module without publication) is saved
 *   in place: nobody sees it yet, so there is nothing to protect.
 * - Content that is published keeps its live values: the changes are merged
 *   into its single draft revision until they are published.
 */
class SaveDraft
{
    public function __construct(
        protected SaveModuleContent $saveModuleContent,
        protected GetFormData $getFormData,
        protected AdminPhp $adminPhp,
        protected SyncAssetUsages $syncAssetUsages,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Module $module, Model $model, array $input): Model
    {
        if (! $module->isPublishable() || ! $model->isPublished()) {
            return $this->saveModuleContent->handle($module, $input, $model);
        }

        $validated = $module->validate($input, onlyPresentFields: true);

        $model->getConnection()->transaction(function () use ($module, $model, $validated): void {
            // Serializes concurrent saves so a content never gets two drafts.
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->first();

            $draft = $model->draftRevision()->first();

            $payload = [
                ...$this->getFormData->handle($module, $model),
                ...($draft->payload ?? []),
                ...$validated,
            ];

            $userId = $this->adminPhp->user()?->getKey();

            if ($draft === null) {
                $model->draftRevision()->create(['status' => RevisionStatus::Draft, 'payload' => $payload, 'user_id' => $userId]);
            } else {
                $draft->update(['payload' => $payload, 'user_id' => $userId]);
            }

            $this->syncAssetUsages->forItem($module, $model);
        });

        return $model;
    }
}
