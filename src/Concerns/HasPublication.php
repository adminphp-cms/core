<?php

namespace Gmarineau\AdminPhp\Concerns;

use Gmarineau\AdminPhp\Enums\PublicationStatus;
use Gmarineau\AdminPhp\Enums\RevisionStatus;
use Gmarineau\AdminPhp\Enums\Visibility;
use Gmarineau\AdminPhp\Models\Revision;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Makes a model publishable: status, publication window, pending draft and revision history.
 * Its table needs the columns added by the `$table->publication()` migration macro.
 *
 * @property PublicationStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $publish_start_at
 * @property Carbon|null $publish_end_at
 */
trait HasPublication
{
    public function initializeHasPublication(): void
    {
        $this->mergeCasts([
            'status' => PublicationStatus::class,
            'published_at' => 'datetime',
            'publish_start_at' => 'datetime',
            'publish_end_at' => 'datetime',
        ]);

        $this->attributes['status'] ??= PublicationStatus::Draft->value;
    }

    /**
     * Content the site may show now: published and inside its publication window.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $now = now();

        $query->where($this->qualifyColumn('status'), PublicationStatus::Published->value)
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('publish_start_at'))->orWhere($this->qualifyColumn('publish_start_at'), '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('publish_end_at'))->orWhere($this->qualifyColumn('publish_end_at'), '>', $now));
    }

    public function isPublished(): bool
    {
        return $this->status === PublicationStatus::Published;
    }

    public function visibility(): Visibility
    {
        if (! $this->isPublished()) {
            return Visibility::Draft;
        }

        if ($this->publish_start_at?->isFuture()) {
            return Visibility::Scheduled;
        }

        if ($this->publish_end_at !== null && ! $this->publish_end_at->isFuture()) {
            return Visibility::Expired;
        }

        return Visibility::Live;
    }

    /**
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest()->latest('id');
    }

    /**
     * Pending changes of a published content, not visible on the site yet.
     *
     * @return MorphOne<Revision, $this>
     */
    public function draftRevision(): MorphOne
    {
        return $this->morphOne(Revision::class, 'revisionable')->where('status', RevisionStatus::Draft);
    }
}
