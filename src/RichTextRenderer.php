<?php

namespace AdminPhp;

use Illuminate\Support\HtmlString;

/**
 * Turns the Tiptap JSON document stored by a RichText field into HTML for the site.
 * Every text is escaped and only what the editor can produce is rendered (the tools of
 * RichText::TOOLS): unknown nodes keep their content, unknown marks are dropped.
 */
class RichTextRenderer
{
    /**
     * Links the site may render; anything else (javascript:, data:…) is shown as plain text.
     */
    protected const SAFE_LINK = '~^(https?://|mailto:|tel:|/|#)~i';

    /**
     * @param  mixed  $document  The stored value: a Tiptap document, or null when empty.
     */
    public function render(mixed $document): HtmlString
    {
        return new HtmlString(is_array($document) ? $this->children($document) : '');
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function node(array $node): string
    {
        $children = $this->children($node);

        return match ($node['type'] ?? null) {
            'paragraph' => "<p>{$children}</p>",
            'heading' => $this->heading($node, $children),
            'bulletList' => "<ul>{$children}</ul>",
            'orderedList' => "<ol>{$children}</ol>",
            'listItem' => "<li>{$children}</li>",
            'blockquote' => "<blockquote>{$children}</blockquote>",
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'text' => $this->text($node),
            default => $children,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function children(array $node): string
    {
        $html = '';

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            $html .= is_array($child) ? $this->node($child) : '';
        }

        return $html;
    }

    /**
     * The editor offers levels 2 and 3: the page title is the h1.
     *
     * @param  array<string, mixed>  $node
     */
    protected function heading(array $node, string $children): string
    {
        $level = min(max((int) ($node['attrs']['level'] ?? 2), 2), 3);

        return "<h{$level}>{$children}</h{$level}>";
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function text(array $node): string
    {
        $html = e((string) ($node['text'] ?? ''));

        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $html = match (is_array($mark) ? ($mark['type'] ?? null) : null) {
                'bold' => "<strong>{$html}</strong>",
                'italic' => "<em>{$html}</em>",
                'underline' => "<u>{$html}</u>",
                'strike' => "<s>{$html}</s>",
                'code' => "<code>{$html}</code>",
                'link' => $this->link($mark, $html),
                default => $html,
            };
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    protected function link(array $mark, string $html): string
    {
        $href = trim((string) ($mark['attrs']['href'] ?? ''));

        if (preg_match(self::SAFE_LINK, $href) !== 1) {
            return $html;
        }

        $rel = preg_match('~^https?://~i', $href) === 1 ? ' rel="noopener"' : '';

        return '<a href="'.e($href).'"'.$rel.'>'.$html.'</a>';
    }
}
