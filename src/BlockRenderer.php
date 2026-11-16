<?php

namespace AdminPhp;

use AdminPhp\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * Renders the blocks of a content on the site: each block with its own Blade view,
 * resources/views/blocks/{type}.blade.php by default, which receives $block and $item.
 */
class BlockRenderer
{
    /**
     * @param  Collection<int, Block>  $blocks  In their order.
     * @param  array<string, string>  $views  Views by block type, replacing the default ones.
     */
    public function render(Collection $blocks, Model $item, array $views = []): HtmlString
    {
        $html = '';

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block, $item, $views[$block->type] ?? $this->viewName($block->type));
        }

        return new HtmlString($html);
    }

    /**
     * The default view of a block type: the adminphp.blocks.views folder, then the type.
     */
    public function viewName(string $type): string
    {
        return trim((string) config('adminphp.blocks.views', 'blocks'), '.').'.'.$type;
    }

    protected function renderBlock(Block $block, Model $item, string $view): string
    {
        if (View::exists($view)) {
            return View::make($view, ['block' => $block, 'item' => $item])->render();
        }

        // A block whose view is not written yet: say which file to create while developing,
        // and leave it out of a site in production rather than break the whole page.
        if (! config('app.debug')) {
            return '';
        }

        return sprintf(
            '<div style="border:1px dashed #e5342b;border-radius:6px;padding:12px 16px;color:#e5342b;font:14px/1.5 system-ui,sans-serif">Bloc « %s » sans vue : créez resources/views/%s.blade.php</div>',
            e($block->type),
            e(str_replace('.', '/', $view)),
        );
    }
}
