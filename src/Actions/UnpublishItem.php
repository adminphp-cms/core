<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;

/**
 * Removes a content from the site. A pending draft is applied right away:
 * once the content is offline its changes no longer need to be kept apart.
 */
class UnpublishItem
{
    public function __construct(protected SaveModuleContent $saveModuleContent) {}

    public function handle(Module $module, Model $model): Model
    {
        return $model->getConnection()->transaction(function () use ($module, $model): Model {
            $model->forceFill(['status' => PublicationStatus::Draft])->save();

            $draft = $model->draftRevision()->first();

            if ($draft !== null) {
                $this->saveModuleContent->handle($module, $draft->payload, $model);
                $draft->delete();
            }

            return $model;
        });
    }
}
