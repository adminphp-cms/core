<?php

namespace Gmarineau\AdminPhp\Fields;

use InvalidArgumentException;

/**
 * WYSIWYG text edited with Tiptap and stored as its JSON document.
 * Its toolbar offers every tool by default; toolbar() narrows it down.
 */
class RichText extends Field
{
    /**
     * Every tool the editor knows, in the order of the toolbar.
     */
    public const TOOLS = [
        'history',
        'heading',
        'bold',
        'italic',
        'underline',
        'strike',
        'code',
        'bulletList',
        'orderedList',
        'blockquote',
        'link',
        'horizontalRule',
    ];

    /**
     * @var array<int, string>
     */
    protected array $tools = self::TOOLS;

    public function type(): string
    {
        return 'rich-text';
    }

    /**
     * Limits the toolbar to the given tools, e.g. ['bold', 'italic', 'link'] for a short text.
     * Formatting left out is also refused by the editor (shortcuts, pasted content).
     *
     * @param  array<int, string>  $tools
     */
    public function toolbar(array $tools): static
    {
        $unknownTools = array_diff($tools, self::TOOLS);

        if ($unknownTools !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown rich text tools [%s]. Available: %s.',
                implode(', ', $unknownTools),
                implode(', ', self::TOOLS),
            ));
        }

        $this->tools = array_values(array_intersect(self::TOOLS, $tools));

        return $this;
    }

    protected function valueRules(): array
    {
        return ['array', ...$this->rules];
    }

    protected function schemaAttributes(): array
    {
        return ['tools' => $this->tools];
    }
}
