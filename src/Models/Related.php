<?php

namespace AdminPhp\Models;

use AdminPhp\AdminPhp;
use AdminPhp\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A content chosen in a Browser field of another content, at its position in the field.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $browser_name
 * @property string $related_type
 * @property string $related_id
 * @property int $position
 */
class Related extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'browser_name',
        'related_type',
        'related_id',
        'position',
    ];

    public function getTable(): string
    {
        return app(AdminPhp::class)->table('related');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * Loads the contents behind a list of references ([['type' => 'article', 'id' => …], …]),
     * with one query per type, in the order of the references. References to a content that no
     * longer exists are skipped, and so are unpublished contents when $onlyPublished is true.
     *
     * @param  array<int, mixed>  $references
     * @return Collection<int, Model>
     */
    public static function resolve(array $references, bool $onlyPublished = true): Collection
    {
        $idsByType = [];

        foreach ($references as $reference) {
            if (is_array($reference) && is_string($reference['type'] ?? null) && is_string($reference['id'] ?? null)) {
                $idsByType[$reference['type']][] = $reference['id'];
            }
        }

        $models = [];

        foreach ($idsByType as $type => $ids) {
            $modelClass = Relation::getMorphedModel($type);

            if ($modelClass === null) {
                continue;
            }

            $query = $modelClass::query()->whereKey($ids);

            if ($onlyPublished && in_array(HasPublication::class, class_uses_recursive($modelClass), true)) {
                $query->published();
            }

            foreach ($query->get() as $model) {
                $models["{$type}:{$model->getKey()}"] = $model;
            }
        }

        $ordered = new Collection;

        foreach ($references as $reference) {
            $model = is_array($reference) ? ($models[($reference['type'] ?? '').':'.($reference['id'] ?? '')] ?? null) : null;

            if ($model !== null) {
                $ordered->push($model);
            }
        }

        return $ordered;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
