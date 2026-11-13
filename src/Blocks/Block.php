<?php

namespace AdminPhp\Blocks;

use AdminPhp\Fields\Blocks;
use AdminPhp\Fields\Field;
use AdminPhp\Fields\Slug;
use Illuminate\Support\Str;
use LogicException;

abstract class Block
{
    protected string $title = '';

    /**
     * Version of the content schema, stored with each block to allow content migrations.
     */
    protected int $version = 1;

    /**
     * @return array<int, Field>
     */
    abstract public function form(): array;

    /**
     * Identifier stored in the `type` column, derived from the class name (QuoteBlock => quote).
     */
    public static function type(): string
    {
        return Str::kebab(Str::replaceEnd('Block', '', class_basename(static::class)));
    }

    public function title(): string
    {
        return $this->title !== '' ? $this->title : Str::headline(static::type());
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->form() as $field) {
            if ($field instanceof Blocks || $field instanceof Slug) {
                throw new LogicException(sprintf('Block [%s] cannot contain a %s field.', static::class, class_basename($field)));
            }

            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(string $path, array $content): array
    {
        $rules = [];

        foreach ($this->fields() as $name => $field) {
            $rules = [...$rules, ...$field->validationRules("{$path}.{$name}", $content[$name] ?? null)];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    public function validationAttributes(string $path, array $content): array
    {
        $attributes = [];

        foreach ($this->fields() as $name => $field) {
            $attributes = [...$attributes, ...$field->validationAttributes("{$path}.{$name}", $content[$name] ?? null)];
        }

        return $attributes;
    }

    /**
     * Keeps only the declared fields, normalized for storage.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function dehydrate(array $content): array
    {
        $dehydrated = [];

        foreach ($this->fields() as $name => $field) {
            $dehydrated[$name] = $field->dehydrate($content[$name] ?? null);
        }

        return $dehydrated;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'type' => static::type(),
            'title' => $this->title(),
            'version' => $this->version,
            'fields' => array_values(array_map(fn (Field $field): array => $field->toSchema(), $this->fields())),
        ];
    }
}
