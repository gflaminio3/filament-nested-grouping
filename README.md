# Filament Nested Grouping

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gflaminio3/filament-nested-grouping.svg?style=flat-square)](https://packagist.org/packages/gflaminio3/filament-nested-grouping)
[![Total Downloads](https://img.shields.io/packagist/dt/gflaminio3/filament-nested-grouping.svg?style=flat-square)](https://packagist.org/packages/gflaminio3/filament-nested-grouping)
[![License](https://img.shields.io/packagist/l/gflaminio3/filament-nested-grouping.svg?style=flat-square)](https://packagist.org/packages/gflaminio3/filament-nested-grouping)

This package extends Filament Tables to provide nested (multi-level) grouping capabilities.

## Installation

Install the package via Composer:

```bash
composer require gflaminio3/filament-nested-grouping
```

Register the plugin in your Filament `PanelProvider`:

```php
// app/Providers/Filament/AdminPanelProvider.php

use gflaminio3\FilamentNestedGrouping\Plugins\NestedGroupingPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugins([
                NestedGroupingPlugin::make(),
            ]);
    }
}
```

## Usage

Use the `NestedGroup` class instead of Filament's standard `Group` for multi-level grouping.

```php
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use gflaminio3\FilamentNestedGrouping\Tables\Grouping\NestedGroup;

class ListProducts extends ListRecords
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name'),
                TextColumn::make('sub_category.name'),
                TextColumn::make('name'),
                TextColumn::make('price')->money(),
            ])
            ->groups([
                NestedGroup::make('category.name')
                    ->title('Category / Sub Category') // Optional custom title for the main group
                    ->thenBy('sub_category.name')       // Group by sub-category
                    ->thenByDate('created_at', 'month'), // Group by creation date (monthly)
            ]);
    }
}
```

-   `NestedGroup::make(string $column)`: Starts a new nested group.
-   `->thenBy(string $column)`: Adds an additional grouping level by column.
-   `->thenByDate(string $column, string $precision = 'day')`: Adds an additional grouping level by a date column, specifying `year`, `month`, or `day` precision.
-   `->title(string $title)`: Sets a custom title for the main nested group.

## How it Works

The package extends Filament's `Group` class, introducing a `NestedGroup` to handle multiple grouping levels. It generates a composite key for each nested group and modifies Eloquent queries to include the necessary `GROUP BY` and `ORDER BY` clauses across all levels, including relationships.

## Contributing

Feel free to submit pull requests or report issues.

## License

This project is licensed under the MIT License.