<?php

namespace Gmarineau\AdminPhp\Fields;

class Text extends Field
{
    protected bool $isMultiline = false;

    protected int $maxLength = 255;

    public function type(): string
    {
        return 'text';
    }

    public function multiline(bool $isMultiline = true): static
    {
        $this->isMultiline = $isMultiline;

        if ($isMultiline && $this->maxLength === 255) {
            $this->maxLength = 5000;
        }

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * Trims the text (per locale when translatable); an empty text is stored as null.
     */
    public function dehydrate(mixed $value): mixed
    {
        $clean = fn (mixed $text): mixed => is_string($text) ? (trim($text) === '' ? null : trim($text)) : $text;

        return parent::dehydrate($this->isTranslatable && is_array($value) ? array_map($clean, $value) : $clean($value));
    }

    protected function valueRules(): array
    {
        return ['string', "max:{$this->maxLength}", ...$this->rules];
    }

    protected function schemaAttributes(): array
    {
        return [
            'multiline' => $this->isMultiline,
            'maxLength' => $this->maxLength,
        ];
    }
}
