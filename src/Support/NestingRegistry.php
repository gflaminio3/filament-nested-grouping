<?php

namespace gflaminio3\FilamentNestedGrouping\Support;

final class NestingRegistry
{
    /** @var array<int, array<int, array{type:string,column:string,precision?:string}>> */
    private static array $byObject = [];

    /** @var array<string, array<int, array{type:string,column:string,precision?:string}>> */
    private static array $byColumn = [];

    public static function push(object $group, array $spec, ?string $column = null): void
    {
        $id = spl_object_id($group);
        self::$byObject[$id] ??= [];
        self::$byObject[$id][] = $spec;

        if ($column) {
            self::$byColumn[$column] ??= [];
            self::$byColumn[$column][] = $spec;
        }
    }

    public static function get(object $group): array
    {
        return self::$byObject[spl_object_id($group)] ?? [];
    }

    public static function has(object $group): bool
    {
        return !empty(self::get($group));
    }

    public static function getByColumn(string $column): array
    {
        return self::$byColumn[$column] ?? [];
    }

    public static function hasByColumn(string $column): bool
    {
        return !empty(self::getByColumn($column));
    }
}
