<?php

namespace Gmarineau\AdminPhp\Actions;

use Carbon\CarbonInterface;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Enums\RevisionStatus;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Publishes a content: its pending draft (plus the given changes) is written to the
 * live tables, the publication window is set and a snapshot is kept in the history.
 */
class PublishItem
{
    public function __construct(
        protected SaveModuleContent $saveModuleContent,
        protected GetFormData $getFormData,
        protected AdminPhp $adminPhp,
        protected SyncAssetUsages $syncAssetUsages,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Changes not saved yet, applied on top of the draft.
     *
     * @throws ValidationException
     */
    public function handle(
        Module $module,
        Model $model,
        array $input = [],
        ?CarbonInterface $publishStartAt = null,
        ?CarbonInterface $publishEndAt = null,
    ): Model {
        if (! $module->isPublishable()) {
            throw new LogicException(sprintf('Module [%s] is not publishable: add HasPublication to its model.', $module->name()));
        }

        return $model->getConnection()->transaction(function () use ($module, $model, $input, $publishStartAt, $publishEndAt): Model {
            $draft = $model->draftRevision()->lockForUpdate()->first();
            $changes = [...($draft->payload ?? []), ...$input];

            // What goes online must be complete, not only the fields changed since the last save.
            $module->validate([...$this->getFormData->handle($module, $model), ...$changes]);

            $this->saveModuleContent->handle($module, $changes, $model);

            $model->forceFill([
                'status' => PublicationStatus::Published,
                'published_at' => $model->published_at ?? now(),
                'publish_start_at' => $publishStartAt,
                'publish_end_at' => $publishEndAt,
            ])->save();

            $draft?->delete();
            $this->syncAssetUsages->forItem($module, $model);

            $model->revisions()->create([
                'status' => RevisionStatus::Published,
                'payload' => $this->getFormData->handle($module, $model->fresh()),
                'user_id' => $this->adminPhp->user()?->getKey(),
            ]);

            return $model;
        });
    }
}
