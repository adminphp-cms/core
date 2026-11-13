<?php

namespace AdminPhp\Actions;

use AdminPhp\Fields\Blocks;
use AdminPhp\Fields\Slug;
use AdminPhp\Modules\Module;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a copy of an item, as currently edited (its pending draft included), through the
 * module form like a new item: the copy is unpublished, its title says "(copie)", its slugs
 * are made again from that title and its blocks get new ids.
 */
class DuplicateItem
{
    public const TITLE_SUFFIX = ' (copie)';

    public function __construct(
        protected GetFormData $getFormData,
        protected SaveModuleContent $saveModuleContent,
    ) {}

    public function handle(Module $module, Model $item): Model
    {
        $values = $this->getFormData->handle($module, $item);

        if ($module->isPublishable()) {
            $values = [...$values, ...($item->draftRevision()->first()->payload ?? [])];
        }

        foreach ($module->fields() as $name => $field) {
            if ($field instanceof Slug) {
                $values[$name] = null;
            }

            if ($field instanceof Blocks && is_array($values[$name] ?? null)) {
                $values[$name] = array_map(fn (array $block): array => [...$block, 'id' => null], $values[$name]);
            }
        }

        $titleField = $module->titleField();

        if (isset($values[$titleField])) {
            $values[$titleField] = is_array($values[$titleField])
                ? array_map(fn (mixed $title): mixed => filled($title) ? $title.self::TITLE_SUFFIX : $title, $values[$titleField])
                : $values[$titleField].self::TITLE_SUFFIX;
        }

        return $this->saveModuleContent->handle($module, $values);
    }
}
