<?php

namespace Gmarineau\AdminPhp\Fields;

use DateTimeInterface;
use Gmarineau\AdminPhp\Fields\Concerns\IsNotTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A calendar date (`Y-m-d`), or a moment with withTime(), exchanged with the admin
 * as ISO 8601 and stored in the application time zone.
 */
class Date extends Field
{
    use IsNotTranslatable;

    protected bool $hasTime = false;

    public function type(): string
    {
        return 'date';
    }

    public function withTime(bool $hasTime = true): static
    {
        $this->hasTime = $hasTime;

        return $this;
    }

    public function dehydrate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->hasTime
            ? Carbon::parse($value)->setTimezone(config('app.timezone'))->toIso8601String()
            : Carbon::parse($value)->format('Y-m-d');
    }

    public function valueFromModel(Model $model): ?string
    {
        $value = $model->getAttribute($this->name);

        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $this->hasTime ? $date->toIso8601String() : $date->format('Y-m-d');
    }

    protected function valueRules(): array
    {
        return [$this->hasTime ? 'date' : 'date_format:Y-m-d', ...$this->rules];
    }

    protected function schemaAttributes(): array
    {
        return ['withTime' => $this->hasTime];
    }
}
