<?php

namespace Gmarineau\AdminPhp\Jobs;

use Gmarineau\AdminPhp\Actions\GenerateAssetVariants;
use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Makes the smaller copies of an uploaded image out of the upload request.
 */
class GenerateAssetVariantsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Asset $asset)
    {
        $this->onConnection(config('adminphp.media.queue'));
        $this->afterCommit();
    }

    public function handle(GenerateAssetVariants $generateAssetVariants): void
    {
        $generateAssetVariants->handle($this->asset);
    }
}
