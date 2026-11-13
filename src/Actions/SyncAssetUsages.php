<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Models\Asset;
use Gmarineau\AdminPhp\Models\AssetUsage;
use Gmarineau\AdminPhp\Modules\Module;
use Gmarineau\AdminPhp\Settings\Settings;
use Gmarineau\AdminPhp\Settings\SettingsStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Records which assets a content uses. An item uses the images of its live values and of
 * its pending draft (both can reach the site); the settings use the images of their values.
 */
class SyncAssetUsages
{
    public function __construct(
        protected GetFormData $getFormData,
        protected SettingsStore $settingsStore,
    ) {}

    public function forItem(Module $module, Model $item): void
    {
        $values = $this->getFormData->handle($module, $item);
        $assetIds = $this->assetIds($module, $values);

        if ($module->isPublishable()) {
            $assetIds = [...$assetIds, ...$this->assetIds($module, $item->draftRevision()->first()->payload ?? [])];
        }

        $this->replace($module->name(), (string) $item->getKey(), $assetIds);
    }

    public function forSettings(Settings $settings): void
    {
        $this->replace(AssetUsage::SETTINGS, AssetUsage::SETTINGS, $this->assetIds($settings, $this->settingsStore->formValues($settings)));
    }

    public function forget(Module $module, Model $item): void
    {
        AssetUsage::query()
            ->where('usable_type', $module->name())
            ->where('usable_id', (string) $item->getKey())
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    protected function assetIds(Module|Settings $form, array $values): array
    {
        $ids = [];

        foreach ($form->fields() as $name => $field) {
            if (array_key_exists($name, $values)) {
                $ids = [...$ids, ...$field->assetIds($values[$name])];
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, string>  $assetIds
     */
    protected function replace(string $usableType, string $usableId, array $assetIds): void
    {
        $existingIds = $assetIds === [] ? [] : Asset::query()->whereKey(array_unique($assetIds))->pluck('id')->all();

        AssetUsage::query()
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId)
            ->whereNotIn('asset_id', $existingIds)
            ->delete();

        foreach ($existingIds as $assetId) {
            AssetUsage::query()->firstOrCreate(['asset_id' => $assetId, 'usable_type' => $usableType, 'usable_id' => $usableId]);
        }
    }
}
