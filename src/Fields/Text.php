<?php

namespace AdminPhp\Fields;

use LogicException;

class Text extends Field
{
    protected string $inputType = 'text';

    protected bool $isMultiline = false;

    protected int $maxLength = 255;

    protected int|float|null $min = null;

    protected int|float|null $max = null;

    protected int|float|null $step = null;

    public function type(): string
    {
        return 'text';
    }

    public function multiline(bool $isMultiline = true): static
    {
        $this->ensureSingleLineType($isMultiline ? 'multiline' : null);
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
     * An e-mail address, checked when saved.
     */
    public function email(): static
    {
        return $this->inputType('email');
    }

    /**
     * A web address (http or https), checked when saved.
     */
    public function url(): static
    {
        return $this->inputType('url');
    }

    /**
     * A phone number: the phone keyboard in the admin, free format.
     */
    public function tel(): static
    {
        return $this->inputType('tel');
    }

    /**
     * A number, stored as a number, optionally between $min and $max. A $step of 1 only
     * accepts whole numbers; without a step, decimals are accepted.
     */
    public function number(int|float|null $min = null, int|float|null $max = null, int|float|null $step = null): static
    {
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;

        return $this->inputType('number');
    }

    public function getInputType(): string
    {
        return $this->inputType;
    }

    /**
     * Trims the text (per locale when translatable); an empty text is stored as null,
     * a number as an int or a float.
     */
    public function dehydrate(mixed $value): mixed
    {
        $clean = function (mixed $text): mixed {
            if (! is_string($text)) {
                return $text;
            }

            $text = trim($text);

            if ($text === '') {
                return null;
            }

            return $this->inputType === 'number' && is_numeric($text) ? $text + 0 : $text;
        };

        return parent::dehydrate($this->isTranslatable && is_array($value) ? array_map($clean, $value) : $clean($value));
    }

    protected function valueRules(): array
    {
        $rules = match ($this->inputType) {
            'email' => ['string', 'email', "max:{$this->maxLength}"],
            'url' => ['string', 'url:http,https', "max:{$this->maxLength}"],
            'number' => [
                $this->step !== null && floor($this->step) == $this->step ? 'integer' : 'numeric',
                ...($this->min !== null ? ["min:{$this->min}"] : []),
                ...($this->max !== null ? ["max:{$this->max}"] : []),
            ],
            default => ['string', "max:{$this->maxLength}"],
        };

        return [...$rules, ...$this->rules];
    }

    protected function schemaAttributes(): array
    {
        return [
            'inputType' => $this->inputType,
            'multiline' => $this->isMultiline,
            'maxLength' => $this->maxLength,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
        ];
    }

    protected function inputType(string $inputType): static
    {
        $this->ensureSingleLineType($this->isMultiline ? 'multiline' : null, $inputType);
        $this->inputType = $inputType;

        return $this;
    }

    /**
     * A multiline text is free text: it cannot also be an e-mail, an address or a number.
     */
    protected function ensureSingleLineType(?string $multiline, ?string $inputType = null): void
    {
        $inputType ??= $this->inputType;

        if ($multiline !== null && $inputType !== 'text') {
            throw new LogicException(sprintf('Text field [%s] cannot be both multiline and of type [%s].', $this->name, $inputType));
        }
    }
}
