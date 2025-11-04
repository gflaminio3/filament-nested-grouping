<?php

namespace gflaminio3\FilamentNestedGrouping\Plugins;

use Filament\Panel;
use Filament\Contracts\Plugin;

class NestedGroupingPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }
    public function getId(): string
    {
        return 'filament-nested-grouping';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
