<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\ListMediaUsages;
use Gmarineau\AdminPhp\Actions\StoreMedia;
use Gmarineau\AdminPhp\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\File;

/**
 * The media library: list, search, upload and delete images.
 */
class MediaController
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $medias = Media::query()
            ->withCount('usages')
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%", caseSensitive: false))
            ->latest()
            ->latest('id')
            ->paginate(48);

        return response()->json([
            'data' => $medias->getCollection()->map(fn (Media $media): array => [...$media->toAdminArray(), 'usageCount' => $media->usages_count])->all(),
            'meta' => [
                'currentPage' => $medias->currentPage(),
                'lastPage' => $medias->lastPage(),
                'total' => $medias->total(),
            ],
        ]);
    }

    /**
     * The media with the contents using it.
     */
    public function show(ListMediaUsages $listMediaUsages, string $id): JsonResponse
    {
        $media = Media::query()->findOrFail($id);
        $usages = $listMediaUsages->handle($media);

        return response()->json(['data' => [...$media->toAdminArray(), 'usageCount' => count($usages), 'usages' => $usages]]);
    }

    /**
     * Deletes a media and its files, unless a content still uses it: a deleted image
     * would leave a hole on the site.
     */
    public function destroy(string $id): Response
    {
        $media = Media::query()->findOrFail($id);
        $usageCount = $media->usages()->count();

        abort_if($usageCount > 0, 409, sprintf(
            'Cette image est utilisée par %d %s : retirez-la d’abord.',
            $usageCount,
            $usageCount > 1 ? 'contenus' : 'contenu',
        ));

        $media->deleteWithFiles();

        return response()->noContent();
    }

    public function store(Request $request, StoreMedia $storeMedia): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                File::image()
                    ->types(config('adminphp.media.mimes', ['jpg', 'jpeg', 'png', 'webp', 'gif']))
                    ->max((int) config('adminphp.media.max_size', 10240)),
            ],
        ]);

        $media = $storeMedia->handle($request->file('file'));

        return response()->json(['data' => $media->toAdminArray()], 201);
    }
}
