<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\ManageEditLock;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Gmarineau\AdminPhp\Models\Lock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemLockController
{
    use ResolvesModuleItems;

    public function __construct(protected AdminPhp $adminPhp, protected ManageEditLock $manageEditLock) {}

    /**
     * Takes or renews the edit lock of the item; with `takeOver`, takes it from someone else.
     * The editor calls it when it opens and every 30 seconds while it stays open.
     */
    public function store(Request $request, string $module, string $id): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        $lock = $this->manageEditLock->acquire($this->findItem($moduleDefinition, $id), $this->adminPhp->user(), $request->boolean('takeOver'));

        return response()->json(['data' => $this->lockData($lock)]);
    }

    /**
     * Releases the lock when the editor closes.
     */
    public function destroy(string $module, string $id): Response
    {
        $moduleDefinition = $this->findModule($module);

        $this->manageEditLock->release($this->findItem($moduleDefinition, $id), $this->adminPhp->user());

        return response()->noContent();
    }

    /**
     * @return array{isMine: bool, userName: string|null, lockedAt: string}
     */
    protected function lockData(Lock $lock): array
    {
        return [
            'isMine' => $lock->user_id === $this->adminPhp->user()?->getKey(),
            'userName' => $lock->user?->name,
            'lockedAt' => $lock->locked_at->toIso8601String(),
        ];
    }
}
