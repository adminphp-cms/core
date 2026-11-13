<?php

namespace AdminPhp\Tests\Fixtures;

use AdminPhp\Concerns\HasNesting;
use AdminPhp\Concerns\HasPosition;
use AdminPhp\Concerns\HasRelated;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MenuLink extends Model
{
    use HasNesting;
    use HasPosition;
    use HasRelated;
    use HasTranslations;
    use HasUlids;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['title'];
}
