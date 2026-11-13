<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\ListAssetUsages;
use Gmarineau\AdminPhp\Actions\StoreAsset;
use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\File;

/**
 * The media library: list, search, upload and delete images.
 */
class AssetController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $assets = Asset::query()
            ->withCount('usages')
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%", caseSensitive: false))
            ->latest()
            ->latest('id')
            ->paginate(48);

        return response()->json([
            'data' => $assets->getCollection()->map(fn (Asset $asset): array => [...$asset->toAdminArray(), 'usageCount' => $asset->usages_count])->all(),
            'meta' => [
                'currentPage' => $assets->currentPage(),
                'lastPage' => $assets->lastPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    /**
     * The asset with the contents using it.
     */
    public function show(ListAssetUsages $listAssetUsages, string $id): JsonResponse
    {
        $asset = Asset::query()->findOrFail($id);
        $usages = $listAssetUsages->handle($asset);

        return response()->json(['data' => [...$asset->toAdminArray(), 'usageCount' => count($usages), 'usages' => $usages]]);
    }

    /**
     * Deletes an asset and its files, unless a content still uses it: a deleted image
     * would leave a hole on the site.
     */
    public function destroy(string $id): Response
    {
        $asset = Asset::query()->findOrFail($id);
        $usageCount = $asset->usages()->count();

        abort_if($usageCount > 0, 409, sprintf(
            'Cette image est utilisée par %d %s : retirez-la d’abord.',
            $usageCount,
            $usageCount > 1 ? 'contenus' : 'contenu',
        ));

        $asset->deleteWithFiles();

        return response()->noContent();
    }

    public function store(Request $request, StoreAsset $storeAsset): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::image()
                    ->types(config('adminphp.media.mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']))
                    ->max((int) config('adminphp.media.max_size', 10240)),
            ],
        ]);

        $asset = $storeAsset->handle($request->file('file'));

        return response()->json(['data' => $asset->toAdminArray()], 201);
    }
}
