<?php

namespace AdminPhp\Models;

use AdminPhp\AdminPhp;
use Illuminate\Database\Eloquent\Model;

/**
 * One stored setting. Translatable settings hold one value per locale.
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('settings');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
