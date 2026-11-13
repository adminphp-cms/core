<?php

namespace Gmarineau\AdminPhp\Models;

use Gmarineau\AdminPhp\AdminPhp;
use Gmarineau\AdminPhp\RichTextRenderer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\HtmlString;

/**
 * @property string $id
 * @property string $blockable_type
 * @property string $blockable_id
 * @property string $editor_name
 * @property string|null $parent_id
 * @property string|null $child_key
 * @property string $type
 * @property int $schema_version
 * @property int $position
 * @property array<string, mixed> $content
 */
class Block extends Model
{
    use HasUlids;

    protected $fillable = [
        'editor_name',
        'parent_id',
        'child_key',
        'type',
        'schema_version',
        'position',
        'content',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('blocks');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'position' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * Value of a content field for the site: a translated field gives its value in the
     * given locale (current one by default), else in the default locale, else the first filled one.
     * An empty value gives null.
     */
    public function translated(string $field, ?string $locale = null): mixed
    {
        return app(AdminPhp::class)->translate($this->content[$field] ?? null, $locale);
    }

    /**
     * HTML of a RichText field of the block, every text escaped, translated like translated().
     */
    public function richText(string $field, ?string $locale = null): HtmlString
    {
        return app(RichTextRenderer::class)->render($this->translated($field, $locale));
    }

    /**
     * Contents chosen in a Browser field of the block, in their chosen order. Only the ones
     * the site may show are returned, unless $onlyPublished is false.
     *
     * @return Collection<int, Model>
     */
    public function related(string $field, bool $onlyPublished = true): Collection
    {
        $references = $this->content[$field] ?? [];

        return Related::resolve(is_array($references) ? $references : [], $onlyPublished);
    }

    /**
     * Media chosen in an Image field of the block, e.g. to render
     * <img src="{{ $block->media('image')?->url() }}">.
     */
    public function media(string $field): ?Media
    {
        $id = $this->content[$field] ?? null;

        return is_string($id) && $id !== '' ? Media::query()->find($id) : null;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Block, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Block, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
