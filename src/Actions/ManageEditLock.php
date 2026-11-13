<?php

namespace Gmarineau\AdminPhp\Actions;

use Gmarineau\AdminPhp\Models\Lock;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Edit locks: one admin user edits an item at a time; the others see it read-only and
 * may take over. A lock that is no longer renewed expires after `adminphp.locks.ttl`.
 */
class ManageEditLock
{
    /**
     * Takes or renews the lock of the item for the user, unless someone else holds it
     * (then it is left alone unless $takeOver). Gives back the lock as it now stands.
     */
    public function acquire(Model $item, User $user, bool $takeOver = false): Lock
    {
        $lock = $this->lockOf($item);
        $now = now();

        if ($lock !== null && $lock->user_id !== $user->getKey() && $lock->isActive() && ! $takeOver) {
            return $lock;
        }

        if ($lock === null) {
            $lock = new Lock;
            $lock->lockable()->associate($item);
        }

        $isNewHolder = $lock->user_id !== $user->getKey() || ! $lock->isActive();

        $lock->fill([
            'user_id' => $user->getKey(),
            'locked_at' => $isNewHolder ? $now : $lock->locked_at,
            'renewed_at' => $now,
        ]);

        try {
            $lock->save();
        } catch (UniqueConstraintViolationException) {
            // Someone took the lock between our read and our write.
            return $this->lockOf($item);
        }

        return $lock;
    }

    /**
     * Releases the lock if the user holds it.
     */
    public function release(Model $item, User $user): void
    {
        Lock::query()->whereMorphedTo('lockable', $item)->where('user_id', $user->getKey())->delete();
    }

    /**
     * The active lock held by someone other than the user, if any.
     */
    public function heldByOther(Model $item, ?User $user): ?Lock
    {
        $lock = $this->lockOf($item);

        return $lock !== null && $lock->isActive() && $lock->user_id !== $user?->getKey() ? $lock : null;
    }

    /**
     * Active locks of the given items, by item key, with their user.
     *
     * @param  array<int, Model>  $items
     * @return array<string, Lock>
     */
    public function activeLocks(array $items): array
    {
        if ($items === []) {
            return [];
        }

        return Lock::query()
            ->with('user:id,name')
            ->where('lockable_type', $items[0]->getMorphClass())
            ->whereIn('lockable_id', array_map(fn (Model $item): string => (string) $item->getKey(), $items))
            ->get()
            ->filter(fn (Lock $lock): bool => $lock->isActive())
            ->keyBy('lockable_id')
            ->all();
    }

    protected function lockOf(Model $item): ?Lock
    {
        /** @var Collection<int, Lock> $locks */
        $locks = Lock::query()->with('user:id,name')->whereMorphedTo('lockable', $item)->get();

        return $locks->first();
    }
}
