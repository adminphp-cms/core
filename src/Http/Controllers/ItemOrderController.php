<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\ReorderItems;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemOrderController
{
    use ResolvesModuleItems;

    /**
     * Saves the order chosen by drag and drop in the listing of a sortable module.
     */
    public function update(Request $request, ReorderItems $reorderItems, string $module): Response
    {
        $moduleDefinition = $this->findModule($module);

        abort_unless($moduleDefinition->isSortable(), 404);

        $reorderItems->handle($moduleDefinition, (array) $request->input('items', []));

        return response()->noContent();
    }
}
