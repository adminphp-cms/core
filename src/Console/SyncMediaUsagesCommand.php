<?php

namespace Gmarineau\AdminPhp\Console;

use Gmarineau\AdminPhp\Actions\SyncMediaUsages;
use Gmarineau\AdminPhp\AdminPhp;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:media:sync-usages')]
class SyncMediaUsagesCommand extends Command
{
    protected $signature = 'adminphp:media:sync-usages';

    protected $description = 'Record again which contents use each media library image, e.g. after installing this feature or importing content';

    public function handle(AdminPhp $adminPhp, SyncMediaUsages $syncMediaUsages): int
    {
        $total = 0;

        foreach ($adminPhp->modules() as $module) {
            $query = $module->modelClass()::query();

            if ($module->isTrashable()) {
                $query->withTrashed();
            }

            foreach ($query->lazyById() as $item) {
                /** @var Model $item */
                $syncMediaUsages->forItem($module, $item);
                $total++;
            }
        }

        if (($settings = $adminPhp->settingsForm()) !== null) {
            $syncMediaUsages->forSettings($settings);
        }

        $this->components->info("Checked {$total} ".str('content')->plural($total).'.');

        return self::SUCCESS;
    }
}
