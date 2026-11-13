<?php

namespace AdminPhp\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasUlids;
}
