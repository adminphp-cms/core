<?php

namespace Gmarineau\AdminPhp\Http\Controllers;

use Gmarineau\AdminPhp\Actions\BuildPreview;
use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\Http\Controllers\Concerns\ResolvesModuleItems;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Illuminate\View\ViewException;
use Throwable;

class ItemPreviewController
{
    use ResolvesModuleItems;

    /**
     * Renders the site's view of an item with the values of the admin form, nothing saved.
     * Body: `values` (the form) and `locale` (the language to preview). Without `id`, previews a new item.
     */
    public function __invoke(Request $request, BuildPreview $buildPreview, string $module, ?string $id = null): JsonResponse
    {
        $moduleDefinition = $this->findModule($module);

        abort_unless($moduleDefinition->isPreviewable(), 404);

        $validated = $request->validate([
            'values' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', Rule::in(app(AdminPhp::class)->locales())],
        ]);

        $item = $id === null ? null : $this->findItem($moduleDefinition, $id);

        // The site renders in the previewed language, the admin's default one unless another is asked.
        App::setLocale($validated['locale'] ?? app(AdminPhp::class)->defaultLocale());

        try {
            $preview = $moduleDefinition->preview($buildPreview->handle($moduleDefinition, $validated['values'] ?? [], $item));
            $html = $preview instanceof Renderable ? $preview->render() : $preview;
        } catch (Throwable $exception) {
            report($exception);

            // A failing view wraps the original error with the view's file path, which editors do not need.
            while ($exception instanceof ViewException && $exception->getPrevious() !== null) {
                $exception = $exception->getPrevious();
            }

            return response()->json(['message' => 'L’aperçu n’a pas pu être affiché : '.$exception->getMessage()], 422);
        }

        return response()->json(['data' => ['html' => $html]]);
    }
}
