<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Concerns\HasNesting;
use AdminPhp\Concerns\HasPosition;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Page extends Model
{
    use HasNesting;
    use HasPosition;
    use HasTranslations;
    use HasUlids;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['title'];
}
