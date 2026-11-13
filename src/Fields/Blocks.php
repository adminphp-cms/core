<?php

namespace AdminPhp\Fields;

use AdminPhp\Actions\SyncBlocks;
use AdminPhp\Blocks\Block as BlockDefinition;
use AdminPhp\Models\Block;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A zone of blocks on a model. The field name is stored as the blocks' `editor_name`.
 */
class Blocks extends Field
{
    /**
     * @var array<int, class-string<BlockDefinition>>
     */
    protected array $blockClasses = [];

    public function type(): string
    {
        return 'blocks';
    }

    public function listingType(): ?string
    {
        return null;
    }

    /**
     * @param  array<int, class-string<BlockDefinition>>  $blockClasses
     */
    public function allow(array $blockClasses): static
    {
        $this->blockClasses = $blockClasses;

        return $this;
    }

    public function translatable(bool $isTranslatable = true): static
    {
        if ($isTranslatable) {
            throw new LogicException('A Blocks field cannot be translatable: translate the fields of its blocks instead.');
        }

        return $this;
    }

    /**
     * @return array<string, BlockDefinition>
     */
    public function blocks(): array
    {
        $blocks = [];

        foreach ($this->blockClasses as $blockClass) {
            $blocks[$blockClass::type()] = new $blockClass;
        }

        return $blocks;
    }

    public function block(string $type): ?BlockDefinition
    {
        return $this->blocks()[$type] ?? null;
    }

    public function validationRules(string $path, mixed $value): array
    {
        $blocks = $this->blocks();

        $rules = [
            $path => [$this->isRequired ? 'required' : 'nullable', 'array', 'list'],
            "{$path}.*" => ['array'],
            "{$path}.*.id" => ['nullable', 'string', 'ulid'],
            "{$path}.*.type" => ['required', 'string', Rule::in(array_keys($blocks))],
            "{$path}.*.content" => ['nullable', 'array'],
        ];

        foreach (is_array($value) ? $value : [] as $index => $item) {
            $block = is_array($item) && is_string($item['type'] ?? null) ? ($blocks[$item['type']] ?? null) : null;

            if ($block === null) {
                continue;
            }

            $content = is_array($item['content'] ?? null) ? $item['content'] : [];

            $rules = [...$rules, ...$block->validationRules("{$path}.{$index}.content", $content)];
        }

        return $rules;
    }

    public function validationAttributes(string $path, mixed $value): array
    {
        $blocks = $this->blocks();

        $attributes = [$path => $this->getLabel()];

        foreach (is_array($value) ? $value : [] as $index => $item) {
            $block = is_array($item) && is_string($item['type'] ?? null) ? ($blocks[$item['type']] ?? null) : null;

            $attributes["{$path}.{$index}.type"] = 'type';

            if ($block !== null) {
                $content = is_array($item['content'] ?? null) ? $item['content'] : [];

                $attributes = [...$attributes, ...$block->validationAttributes("{$path}.{$index}.content", $content)];
            }
        }

        return $attributes;
    }

    public function mediaIds(mixed $value): array
    {
        $ids = [];

        foreach (is_array($value) ? $value : [] as $item) {
            $block = is_array($item) && is_string($item['type'] ?? null) ? $this->block($item['type']) : null;
            $content = is_array($item['content'] ?? null) ? $item['content'] : [];

            foreach ($block?->fields() ?? [] as $name => $field) {
                $ids = [...$ids, ...$field->mediaIds($content[$name] ?? null)];
            }
        }

        return $ids;
    }

    public function fillModel(Model $model, mixed $value): void {}

    /**
     * Shows the blocks of the zone through the model's `blocks` relation (HasBlocks),
     * after the blocks of the zones filled before it.
     */
    public function fillPreview(Model $model, mixed $value): void
    {
        $blocks = $model->relationLoaded('blocks') ? $model->getRelation('blocks') : new Collection;

        foreach (array_values(is_array($value) ? $value : []) as $position => $item) {
            $definition = is_array($item) && is_string($item['type'] ?? null) ? $this->block($item['type']) : null;

            if ($definition === null) {
                continue;
            }

            $block = new Block([
                'editor_name' => $this->name,
                'type' => $item['type'],
                'schema_version' => $definition->version(),
                'position' => $position,
                'content' => $definition->dehydrate(is_array($item['content'] ?? null) ? $item['content'] : []),
            ]);
            $block->id = is_string($item['id'] ?? null) ? $item['id'] : (string) Str::ulid();

            $blocks->push($block);
        }

        $model->setRelation('blocks', $blocks);
    }

    public function afterModelSaved(Model $model, mixed $value): void
    {
        app(SyncBlocks::class)->handle($model, $this, is_array($value) ? $value : []);
    }

    /**
     * @return array<int, array{id: string, type: string, content: array<string, mixed>}>
     */
    public function valueFromModel(Model $model): array
    {
        return Block::query()
            ->whereMorphedTo('blockable', $model)
            ->where('editor_name', $this->name)
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get()
            ->map(fn (Block $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'content' => $block->content,
            ])
            ->all();
    }

    protected function schemaAttributes(): array
    {
        return [
            'blocks' => array_values(array_map(
                fn (BlockDefinition $block): array => $block->toSchema(),
                $this->blocks(),
            )),
        ];
    }
}
