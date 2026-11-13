<?php

namespace AdminPhp\Actions;

use AdminPhp\Models\Media;
use AdminPhp\Models\MediaUsage;
use AdminPhp\Modules\Module;
use AdminPhp\Settings\Settings;
use AdminPhp\Settings\SettingsStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Records which medias a content uses. An item uses the images of its live values and of
 * its pending draft (both can reach the site); the settings use the images of their values.
 */
class SyncMediaUsages
{
    public function __construct(
        protected GetFormData $getFormData,
        protected SettingsStore $settingsStore,
    ) {}

    public function forItem(Module $module, Model $item): void
    {
        $values = $this->getFormData->handle($module, $item);
        $mediaIds = $this->mediaIds($module, $values);

        if ($module->isPublishable()) {
            $mediaIds = [...$mediaIds, ...$this->mediaIds($module, $item->draftRevision()->first()->payload ?? [])];
        }

        $this->replace($module->name(), (string) $item->getKey(), $mediaIds);
    }

    public function forSettings(Settings $settings): void
    {
        $this->replace(MediaUsage::SETTINGS, MediaUsage::SETTINGS, $this->mediaIds($settings, $this->settingsStore->formValues($settings)));
    }

    public function forget(Module $module, Model $item): void
    {
        MediaUsage::query()
            ->where('usable_type', $module->name())
            ->where('usable_id', (string) $item->getKey())
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    protected function mediaIds(Module|Settings $form, array $values): array
    {
        $ids = [];

        foreach ($form->fields() as $name => $field) {
            if (array_key_exists($name, $values)) {
                $ids = [...$ids, ...$field->mediaIds($values[$name])];
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, string>  $mediaIds
     */
    protected function replace(string $usableType, string $usableId, array $mediaIds): void
    {
        $existingIds = $mediaIds === [] ? [] : Media::query()->whereKey(array_unique($mediaIds))->pluck('id')->all();

        MediaUsage::query()
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId)
            ->whereNotIn('media_id', $existingIds)
            ->delete();

        foreach ($existingIds as $mediaId) {
            MediaUsage::query()->firstOrCreate(['media_id' => $mediaId, 'usable_type' => $usableType, 'usable_id' => $usableId]);
        }
    }
}
