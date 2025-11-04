<?php

namespace gflaminio3\FilamentNestedGrouping\Support;

use Filament\Tables\Table;

final class TableContext
{
    /** @var array<int, Table> */
    private static array $stack = [];

    public static function push(Table $table): void
    {
        self::$stack[] = $table;
    }

    public static function last(): ?Table
    {
        return self::$stack ? self::$stack[array_key_last(self::$stack)] : null;
    }

    public static function reset(): void
    {
        self::$stack = [];
    }
}
