<?php

namespace Cloudcake\Components\Enums\Concerns;

use Filament\Support\Enums\IconSize;

trait HasIconQuery
{
    /**
     * Get the icon name for the given size.
     */
    public function getIconForSize(IconSize $size): string
    {
        return $this->value;
    }

    /**
     * Get the icon name as a string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Get all available icons.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'value', 'name');
    }

    /**
     * Search for icons by name.
     *
     * @return array<self>
     */
    public static function search(string $query): array
    {
        return array_filter(
            self::cases(),
            fn(self $icon) => str_contains(strtolower($icon->name), strtolower($query)) || str($icon->value)->after('-')->contains(strtolower($query))
        );
    }
}