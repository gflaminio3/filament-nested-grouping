<?php

namespace gflaminio3\FilamentNestedGrouping\Tables\Grouping;

use Carbon\CarbonInterface;
use Closure;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class NestedGroup extends Group
{
    /**
     * @var array<int, array{column:string, type?:'column'|'date', precision?:'year'|'month'|'day'}>
     */
    protected array $nestedSpecs = [];

    public static function make(?string $column = null): static
    {
        $static = app(static::class, ['id' => $column]);
        $static->configure()->column($column);

        return $static;
    }

    public function thenBy(string $column): static
    {
        $this->nestedSpecs[] = ['type' => 'column', 'column' => $column];

        return $this;
    }

    public function thenByDate(string $column, string $precision = 'day'): static
    {
        $this->nestedSpecs[] = ['type' => 'date', 'column' => $column, 'precision' => $precision];

        return $this;
    }

    public function getNestedSpecs(): array
    {
        return $this->nestedSpecs;
    }

    public function hasNested(): bool
    {
        return !empty($this->nestedSpecs);
    }

    /* CHIAVE COMPOSTA */

    public function getStringKey(Model $record): ?string
    {
        $values = [];

        // L1 (this)
        $values[] = $this->normalizeKeyValue($this->extractValue($record, $this->getColumn()), $this->isDate());

        // L2..Ln
        foreach ($this->nestedSpecs as $spec) {
            $val = $this->extractValue($record, $spec['column'] ?? '');
            $values[] = $this->normalizeKeyValue(
                $val,
                ($spec['type'] ?? 'column') === 'date'
            );
        }

        if (empty(array_filter($values, fn($v) => $v !== null && $v !== ''))) {
            return null;
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE);
    }

    public function getTitle(Model $record): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $parts = [];

        // L1: usa la logica del parent Group
        $titleCallback = $this->getTitleFromRecordUsing(fn($record) => $record->getTitle());
        if ($titleCallback instanceof Closure) {
            $parts[] = $this->evaluate($titleCallback, [
                'record' => $record,
                    $record::class => $record,
            ]);
        } else {
            $val = $this->extractValue($record, $this->getColumn());
            $parts[] = $this->formatValue($val, $this->isDate());
        }

        // L2..Ln
        foreach ($this->nestedSpecs as $spec) {
            $val = $this->extractValue($record, $spec['column'] ?? '');
            $parts[] = $this->formatValue($val, ($spec['type'] ?? 'column') === 'date');
        }

        return implode(' / ', array_map(fn($p) => $p ?? '(null)', $parts));
    }

    /* QUERY */

    public function groupQuery(BaseBuilder $query, Model $model): BaseBuilder
    {
        $query = parent::groupQuery($query, $model);

        foreach ($this->nestedSpecs as $spec) {
            $column = $this->qualifyColumnForGroup($model, $spec['column']);

            if (($spec['type'] ?? 'column') === 'date') {
                $query->groupByRaw("date({$column})");
            } else {
                $query->groupBy($column);
            }
        }

        return $query;
    }

    public function orderQuery(EloquentBuilder $query, string $direction): EloquentBuilder
    {
        $query = parent::orderQuery($query, $direction);

        foreach ($this->nestedSpecs as $spec) {
            $column = $spec['column'];

            if (($spec['type'] ?? 'column') === 'date') {
                $query->orderBy($this->relationshipAwareAttribute($query->getModel(), $column), $direction);
            } else {
                if ($rel = $this->getRelationship($query->getModel(), $column)) {
                    $query = $query->orderByPowerJoins(
                        $this->getRelationshipName($column) . '.' . $this->getRelationshipAttribute($column),
                        $direction,
                        joinType: 'leftJoin'
                    ); /** @phpstan-ignore method.notFound */
                } else {
                    $query->orderBy($this->getRelationshipAttribute($column), $direction);
                }
            }
        }

        return $query;
    }

    public function scopeQueryByKey(EloquentBuilder $query, ?string $key): EloquentBuilder
    {
        $parts = $this->decodeCompositeKey($key);

        // L1
        $query = parent::scopeQueryByKey($query, $parts[0] ?? null);

        // L2..Ln
        for ($i = 1; $i < count($parts); $i++) {
            $spec = $this->nestedSpecs[$i - 1] ?? null;
            if (!$spec)
                break;

            $col = $spec['column'];
            $value = $parts[$i];

            if (($spec['type'] ?? 'column') === 'date') {
                $this->applyDefaultScopeToQuery($query, $this->relationshipAwareAttribute($query->getModel(), $col), $value);
            } else {
                if ($relName = $this->getRelationshipName($col)) {
                    $attr = $this->getRelationshipAttribute($col);
                    $query->whereHas(
                        $relName,
                        fn(EloquentBuilder $q) => $this->applyDefaultScopeToQuery($q, $attr, $value),
                    )->when(blank($value), fn(EloquentBuilder $q) => $q->orWhereDoesntHave($relName));
                } else {
                    $this->applyDefaultScopeToQuery($query, $col, $value);
                }
            }
        }

        return $query;
    }

    /* HELPERS */

    protected function extractValue(Model $record, string $column): mixed
    {
        if ($this->getKeyFromRecordUsing instanceof Closure) {
            return $this->evaluate(
                $this->getKeyFromRecordUsing,
                namedInjections: ['column' => $column, 'record' => $record],
                typedInjections: [Model::class => $record, $record::class => $record],
            );
        }

        return Arr::get($record, $column);
    }

    protected function normalizeKeyValue(mixed $key, bool $isDate): mixed
    {
        if ($key instanceof \BackedEnum) {
            $key = $key->value;
        }

        if (filled($key) && $isDate) {
            if (!($key instanceof CarbonInterface)) {
                $key = \Carbon\Carbon::parse($key);
            }
            $key = $key->toDateString();
        }

        return filled($key) ? strval($key) : null;
    }

    protected function formatValue(mixed $value, bool $isDate): string
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($isDate && filled($value)) {
            if (!($value instanceof CarbonInterface)) {
                $value = \Carbon\Carbon::parse($value);
            }
            return $value->translatedFormat('M Y');
        }

        return filled($value) ? strval($value) : '(null)';
    }

    protected function decodeCompositeKey(?string $key): array
    {
        if (blank($key))
            return [];

        $decoded = json_decode($key, true);
        return is_array($decoded) ? $decoded : [$key];
    }

    public function getRelationshipName(?string $name = null): ?string
    {
        $name ??= $this->getColumn();

        if (!str($name)->contains('.')) {
            return null;
        }

        return (string) str($name)->beforeLast('.');
    }

    public function getRelationshipAttribute(?string $name = null): string
    {
        $name ??= $this->getColumn();

        if (!str($name)->contains('.')) {
            return $name;
        }

        return (string) str($name)->afterLast('.');
    }

    protected function relationshipAwareAttribute(Model $model, string $column): string
    {
        if ($rel = $this->getRelationship($model, $column)) {
            return $rel->getRelated()->qualifyColumn($this->getRelationshipAttribute($column));
        }

        return $column;
    }

    public function getRelationship(Model $record, ?string $name = null): ?Relation
    {
        if (blank($name) && (!str($this->getColumn())->contains('.'))) {
            return null;
        }

        $relationship = null;
        $target = $name ?? $this->getRelationshipName();

        if (blank($target)) {
            return null;
        }

        foreach (explode('.', $target) as $nested) {
            if ($record->hasAttribute($nested) || !$record->isRelation($nested)) {
                $relationship = null;
                break;
            }

            $relationship = $record->{$nested}();
            $record = $relationship->getRelated();
        }

        return $relationship;
    }

    protected function qualifyColumnForGroup(Model $model, string $column): string
    {
        if (!Str::contains($column, '.')) {
            return $column;
        }

        $rel = $this->getRelationship($model, $column);
        if (!$rel) {
            return $column;
        }

        return $rel->getRelated()->qualifyColumn($this->getRelationshipAttribute($column));
    }
}
