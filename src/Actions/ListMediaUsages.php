<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Models\Media;
use Gmarineau\AdminPhp\Models\MediaUsage;

/**
 * Names the contents using a media, for the media library: one query per module.
 * Usages of a module that is no longer registered, or of a deleted item, are left out.
 */
class ListMediaUsages
{
    public function __construct(protected AdminPhp $adminPhp) {}

    /**
     * @return array<int, array{type: string, id: string|null, title: string, moduleTitle: string, icon: string, isTrashed: bool}>
     */
    public function handle(Media $media): array
    {
        $usages = $media->usages()->get()->groupBy('usable_type');
        $locale = $this->adminPhp->defaultLocale();
        $data = [];

        foreach ($usages as $type => $typeUsages) {
            if ($type === MediaUsage::SETTINGS) {
                $settings = $this->adminPhp->settingsForm();

                if ($settings !== null) {
                    $data[] = ['type' => $type, 'id' => null, 'title' => $settings->title(), 'moduleTitle' => 'Réglages', 'icon' => 'settings', 'isTrashed' => false];
                }

                continue;
            }

            if (! $this->adminPhp->hasModule($type)) {
                continue;
            }

            $module = $this->adminPhp->module($type);
            $query = $module->modelClass()::query();

            if ($module->isTrashable()) {
                $query->withTrashed();
            }

            foreach ($query->whereKey($typeUsages->pluck('usable_id')->all())->get() as $item) {
                $data[] = [
                    'type' => $type,
                    'id' => (string) $item->getKey(),
                    'title' => $module->itemTitle($item, $locale),
                    'moduleTitle' => $module->title(),
                    'icon' => $module->icon(),
                    'isTrashed' => $module->isTrashable() && $item->trashed(),
                ];
            }
        }

        return $data;
    }
}
