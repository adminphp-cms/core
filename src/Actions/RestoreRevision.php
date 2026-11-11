<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Models\Revision;
use Gmarineau\AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Brings back an older version as the current draft. Like any edit, it only
 * reaches the site of a published content once it is published.
 */
class RestoreRevision
{
    public function __construct(protected SaveDraft $saveDraft) {}

    /**
     * @throws ValidationException When the snapshot no longer matches the module form.
     */
    public function handle(Module $module, Model $model, Revision $revision): Model
    {
        return $this->saveDraft->handle($module, $model, $revision->payload);
    }
}
