<?php

namespace Gmarineau\AdminPhp\Console;

use Gmarineau\AdminPhp\Actions\GenerateAssetVariants;
use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:media:regenerate')]
class RegenerateMediaCommand extends Command
{
    protected $signature = 'adminphp:media:regenerate
        {ids?* : Ids of the images to process (all by default)}';

    protected $description = 'Make again the smaller copies of the media library images, e.g. after changing adminphp.media.sizes';

    public function handle(GenerateAssetVariants $generateAssetVariants): int
    {
        $ids = (array) $this->argument('ids');

        $query = Asset::query()->when($ids !== [], fn ($query) => $query->whereKey($ids));
        $total = $query->count();

        if ($total === 0) {
            $this->components->info('No image to process.');

            return self::SUCCESS;
        }

        $this->withProgressBar($query->lazyById(), fn (Asset $asset) => $generateAssetVariants->handle($asset));
        $this->newLine(2);

        $this->components->info("Processed {$total} ".str('image')->plural($total).'.');

        return self::SUCCESS;
    }
}
