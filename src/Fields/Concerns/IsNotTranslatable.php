<?php

namespace AdminPhp\Fields\Concerns;

use LogicException;

/**
 * For fields whose value is the same in every locale (a choice, a flag, a date).
 */
trait IsNotTranslatable
{
    public function translatable(bool $isTranslatable = true): static
    {
        if ($isTranslatable) {
            throw new LogicException(sprintf('A [%s] field cannot be translatable.', $this->type()));
        }

        return $this;
    }
}
