<?php

namespace App\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resolves the transitive table closure of a model's relationships.
 *
 * Used by the sync scope tripwire to compare the committed manifest against
 * what application code actually reaches. A hand-maintained relationship list
 * drifts; walking the model graph does not.
 *
 * Discovery is deliberately conservative. Eloquent has no
 * `Model::getRelationships()`, and invoking every public no-argument method
 * would run mutating accessors (e.g. `Booking::generateRoomAccessTokens()`
 * deletes and recreates rows). A method is treated as a relationship only when
 * its own source body constructs an Eloquent relation, so non-relation methods
 * are never executed.
 */
class SyncScopeResolver
{
    /**
     * Traversal stops at these models once their own table is recorded. They
     * are identity or module boundaries: POS and Inventory read them for a
     * label, a price or an actor, and do not follow them into unrelated
     * modules (staff messaging, room service orders, maintenance). Following
     * them would pull half the hotel database into the closure and make the
     * tripwire fire on changes that are not POS dependencies.
     *
     * @var list<class-string<Model>>
     */
    private const BOUNDARIES = [
        \App\Models\User::class,
        \App\Models\Property::class,
    ];

    /**
     * A relation constructor keyword immediately chained off `$this` or a
     * local `$model`. Matched against the method body only.
     */
    private const RELATION_BODY = '/\$(?:this|model)\s*->\s*(hasMany|hasOne|belongsTo|belongsToMany|'
        . 'morphOne|morphMany|morphTo|morphToMany|morphedByMany|hasManyThrough|hasOneThrough)\s*\(/';

    /** @var array<class-string, true> */
    private array $seen = [];

    /** @var array<string, true> */
    private array $tables = [];

    /**
     * @param  class-string<Model>  $model
     * @return list<string> sorted table names
     */
    public function closureFor(string $model, int $depth = 3): array
    {
        $this->seen = [];
        $this->tables = [];

        if (class_exists($model)) {
            $this->walk($model, $depth);
        }

        $tables = array_keys($this->tables);
        sort($tables);

        return $tables;
    }

    private function walk(string $model, int $depth): void
    {
        if (! class_exists($model) || isset($this->seen[$model])) {
            return;
        }

        $this->seen[$model] = true;

        /** @var Model $instance */
        $instance = new $model;
        $table = $instance->getTable();

        if (Schema::hasTable($table)) {
            $this->tables[$table] = true;
        }

        if ($depth <= 0 || in_array($model, self::BOUNDARIES, true)) {
            return;
        }

        foreach ($this->relations($instance) as $related) {
            $this->walk($related, $depth - 1);
        }
    }

    /**
     * @return list<class-string<Model>>
     */
    private function relations(Model $model): array
    {
        $out = [];
        $ref = new ReflectionClass($model);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }

            if (! $this->methodBodyBuildsRelation($method)) {
                continue;
            }

            try {
                $relation = $model->{$method->getName()}();
            } catch (\Throwable) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            try {
                $related = $relation->getRelated();
            } catch (\Throwable) {
                continue;
            }

            if ($related instanceof Model) {
                $out[] = $related::class;
            }
        }

        return array_values(array_unique($out));
    }

    private function methodBodyBuildsRelation(ReflectionMethod $method): bool
    {
        $file = $method->getFileName();

        if (! $file || $method->isInternal()) {
            return false;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return false;
        }

        $body = implode("\n", array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        return (bool) preg_match(self::RELATION_BODY, $body);
    }
}
