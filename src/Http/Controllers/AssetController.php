<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\StoreAsset;
use Gmarineau\AdminPhp\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

/**
 * The media library: list, search and upload images.
 */
class AssetController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $assets = Asset::query()
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%", caseSensitive: false))
            ->latest()
            ->latest('id')
            ->paginate(48);

        return response()->json([
            'data' => $assets->getCollection()->map(fn (Asset $asset): array => $asset->toAdminArray())->all(),
            'meta' => [
                'currentPage' => $assets->currentPage(),
                'lastPage' => $assets->lastPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => Asset::query()->findOrFail($id)->toAdminArray()]);
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
