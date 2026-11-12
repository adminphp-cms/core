<?php

namespace Gmarineau\AdminPhp\Settings;

use Gmarineau\AdminPhp\Fields\Blocks;
use Gmarineau\AdminPhp\Fields\Field;
use Gmarineau\AdminPhp\Fields\Slug;
use Gmarineau\AdminPhp\Forms\HasFields;
use LogicException;

/**
 * Site-wide values edited in the admin ("Réglages"), such as the homepage or the
 * contact email. Declared like a module form, stored as key/value pairs, read on
 * the site with `app(AdminPhp::class)->setting('homepage')`.
 */
abstract class Settings
{
    use HasFields;

    /**
     * Title of the settings page in the admin.
     */
    protected string $title = 'Réglages';

    /**
     * Short sentence shown under the title.
     */
    protected string $description = '';

    /**
     * Fields that only make sense on a model (they store rows or derive values on save).
     *
     * @var array<int, class-string<Field>>
     */
    protected const UNSUPPORTED_FIELDS = [Blocks::class, Slug::class];

    /**
     * @return array<int, Field>
     */
    abstract public function form(): array;

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @throws LogicException When a field type cannot be stored as a setting.
     */
    public function ensureSupportedFields(): void
    {
        foreach ($this->fields() as $field) {
            foreach (self::UNSUPPORTED_FIELDS as $unsupported) {
                if ($field instanceof $unsupported) {
                    throw new LogicException(sprintf('Setting [%s] cannot be a %s field.', $field->name, class_basename($unsupported)));
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toSchema(): array
    {
        return [
            'title' => $this->title(),
            'description' => $this->description(),
            'fields' => array_values(array_map(fn (Field $field): array => $field->toSchema(), $this->fields())),
        ];
    }
}
