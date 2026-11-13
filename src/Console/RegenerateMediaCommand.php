<?php

namespace AdminPhp\Console;

use AdminPhp\Actions\GenerateMediaVariants;
use AdminPhp\Models\Media;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'adminphp:media:regenerate')]
class RegenerateMediaCommand extends Command
{
    protected $signature = 'adminphp:media:regenerate
        {ids?* : Ids of the images to process (all by default)}';

    protected $description = 'Make again the smaller copies of the media library images, e.g. after changing adminphp.media.sizes';

    public function handle(GenerateMediaVariants $generateMediaVariants): int
    {
        $ids = (array) $this->argument('ids');

        $query = Media::query()->when($ids !== [], fn ($query) => $query->whereKey($ids));
        $total = $query->count();

        if ($total === 0) {
            $this->components->info('No image to process.');

            return self::SUCCESS;
        }

        $this->withProgressBar($query->lazyById(), fn (Media $media) => $generateMediaVariants->handle($media));
        $this->newLine(2);

        $this->components->info("Processed {$total} ".str('image')->plural($total).'.');

        return self::SUCCESS;
    }
}
