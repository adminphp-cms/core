{{--
    The HTML of a RichText field, every text escaped. The value is the stored Tiptap document,
    already translated: e.g. $block->translated('body') or $article->body.

    <x-adminphp::rich-text :content="$block->translated('body')" class="prose" />
--}}
@props(['content'])

@if (is_array($content))
    <div {{ $attributes }}>{{ app(\AdminPhp\RichTextRenderer::class)->render($content) }}</div>
@endif
