<?php

namespace Gmarineau\AdminPhp\Forms;

use Gmarineau\AdminPhp\Fields\Field;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validation of a form declared as a list of fields, shared by modules and settings.
 */
trait HasFields
{
    /**
     * @return array<int, Field>
     */
    abstract public function form(): array;

    /**
     * Validates a submitted form and returns the values of the declared fields only.
     *
     * @param  array<string, mixed>  $input
     * @param  bool  $onlyPresentFields  Validate only the fields present in the input (partial update).
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, bool $onlyPresentFields = false): array
    {
        $validated = Validator::make(
            $input,
            $this->validationRules($input, $onlyPresentFields),
            attributes: $this->validationAttributes($input),
        )->validate();

        return array_intersect_key($validated, $this->fields());
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->form() as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  bool  $onlyPresentFields  Validate only the fields present in the input (partial update).
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $input, bool $onlyPresentFields = false): array
    {
        $rules = [];

        foreach ($this->fields() as $name => $field) {
            if ($onlyPresentFields && ! array_key_exists($name, $input)) {
                continue;
            }

            $rules = [...$rules, ...$field->validationRules($name, $input[$name] ?? null)];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function validationAttributes(array $input): array
    {
        $attributes = [];

        foreach ($this->fields() as $name => $field) {
            $attributes = [...$attributes, ...$field->validationAttributes($name, $input[$name] ?? null)];
        }

        return $attributes;
    }
}
