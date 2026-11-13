<?php

namespace AdminPhp\Jobs;

use AdminPhp\Actions\GenerateMediaVariants;
use AdminPhp\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Makes the smaller copies of an uploaded image out of the upload request.
 */
class GenerateMediaVariantsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Media $media)
    {
        $this->onConnection(config('adminphp.media.queue'));
        $this->afterCommit();
    }

    public function handle(GenerateMediaVariants $generateMediaVariants): void
    {
        $generateMediaVariants->handle($this->media);
    }
}
