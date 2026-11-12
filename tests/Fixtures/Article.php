<?php

namespace Gmarineau\AdminPhp\Tests\Fixtures;

use Gmarineau\AdminPhp\Concerns\HasBlocks;
use Gmarineau\AdminPhp\Concerns\HasPublication;
use Gmarineau\AdminPhp\Concerns\HasRelated;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Article extends Model
{
    use HasBlocks;
    use HasPublication;
    use HasRelated;
    use HasTranslations;
    use HasUlids;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['title', 'lead', 'slug'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => Category::class,
            'tags' => 'array',
            'is_featured' => 'boolean',
            'event_on' => 'date',
            'event_at' => 'datetime',
            'faq' => 'array',
        ];
    }
}
