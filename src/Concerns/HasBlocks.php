<?php

namespace AdminPhp\Concerns;

use AdminPhp\BlockRenderer;
use AdminPhp\Models\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\HtmlString;

/**
 * Gives a model read access to its blocks, and renders them on the site.
 */
trait HasBlocks
{
    /**
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')
            ->whereNull('parent_id')
            ->orderBy('position');
    }

    /**
     * The HTML of the blocks, each rendered by its view (resources/views/blocks/{type}.blade.php),
     * which receives $block and $item: {{ $article->renderBlocks() }}.
     *
     * Give the name of a Blocks field when the model has several, and views by block type
     * to replace the default ones: renderBlocks('sidebar', ['quote' => 'blocks.quote-small']).
     *
     * @param  array<string, string>  $views
     */
    public function renderBlocks(?string $field = null, array $views = []): HtmlString
    {
        $blocks = $this->blocks;

        if ($field !== null) {
            $blocks = $blocks->where('editor_name', $field)->values();
        }

        return app(BlockRenderer::class)->render($blocks, $this, $views);
    }
}
