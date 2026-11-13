<?php

namespace AdminPhp\Modules;

use AdminPhp\AdminPhp;
use AdminPhp\Fields\Field;
use AdminPhp\Models\Media;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

/**
 * A column of a module listing. A column named after a form field takes its label and
 * format from the field (a select shows the option label, a toggle a check mark…);
 * other columns show a model attribute, such as `updated_at`.
 */
class Column
{
    protected ?string $label = null;

    protected bool $isSortable = false;

    protected bool $isStatus = false;

    final public function __construct(public readonly string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * Publication status of the item (online, scheduled, changes to publish…).
     */
    public static function status(): static
    {
        $column = new static('status');
        $column->isStatus = true;
        $column->label = 'Statut';

        return $column;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $isSortable = true): static
    {
        $this->isSortable = $isSortable;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->isSortable && ! $this->isStatus;
    }

    public function isStatus(): bool
    {
        return $this->isStatus;
    }

    public function getLabel(Module $module): string
    {
        return $this->label ?? $this->field($module)?->getLabel() ?? Str::headline($this->name);
    }

    /**
     * title (the column linking to the item), status, text, boolean, date, datetime or image.
     *
     * @throws LogicException When the column shows a field that has no listing format.
     */
    public function type(Module $module): string
    {
        if ($this->isStatus) {
            return 'status';
        }

        if ($this->name === $module->titleField()) {
            return 'title';
        }

        $field = $this->field($module);

        if ($field !== null) {
            return $field->listingType() ?? throw new LogicException(sprintf(
                'Field [%s] of module [%s] cannot be shown as a listing column.',
                $this->name,
                $module->name(),
            ));
        }

        $model = new ($module->modelClass());

        return match (true) {
            in_array($this->name, [$model->getCreatedAtColumn(), $model->getUpdatedAtColumn()], true), $model->hasCast($this->name, ['datetime', 'immutable_datetime']) => 'datetime',
            $model->hasCast($this->name, ['date', 'immutable_date']) => 'date',
            $model->hasCast($this->name, ['bool', 'boolean']) => 'boolean',
            default => 'text',
        };
    }

    /**
     * The value of the column for an item. Image columns give a thumbnail url, looked up
     * in the medias loaded for the page.
     *
     * @param  array<string, Media>  $medias
     */
    public function value(Model $model, Module $module, string $locale, array $medias = []): mixed
    {
        if ($this->isStatus) {
            return null;
        }

        $field = $this->field($module);

        if ($field === null) {
            $value = $model->getAttribute($this->name);

            return match (true) {
                $value instanceof CarbonInterface => $value->toIso8601String(),
                is_bool($value), $value === null => $value,
                $value instanceof BackedEnum => $value->value,
                is_scalar($value) => (string) $value,
                default => null,
            };
        }

        $value = $field->listingValue($model, $locale);

        if ($field->listingType() === 'image') {
            return is_string($value) ? ($medias[$value] ?? null)?->toAdminArray()['thumbnailUrl'] : null;
        }

        return $value;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, Module $module, string $direction): void
    {
        $field = $this->field($module);
        $column = $field?->isTranslatable() ? "{$this->name}->".app(AdminPhp::class)->defaultLocale() : $this->name;

        $query->orderBy($column, $direction);
    }

    /**
     * @return array{name: string, label: string, type: string, sortable: bool}
     */
    public function toSchema(Module $module): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel($module),
            'type' => $this->type($module),
            'sortable' => $this->isSortable(),
        ];
    }

    protected function field(Module $module): ?Field
    {
        return $this->isStatus ? null : ($module->fields()[$this->name] ?? null);
    }
}
