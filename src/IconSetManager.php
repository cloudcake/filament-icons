<?php

namespace Cloudcake\Icons;

use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionException;

class IconSetManager
{
    protected IconFactory $iconFactory;

    public function __construct()
    {
        $this->iconFactory = app(IconFactory::class);
    }

    /**
     * Get all available icon sets from the blade-icons factory.
     *
     * @return array<string, array<string, mixed>>
     * @throws ReflectionException
     */
    public function getSets(): array
    {
        $sets = [];

        // Use reflection to access the protected sets property from the Factory
        $reflection = new ReflectionClass($this->iconFactory);

        if ($reflection->hasProperty('sets')) {
            $setsProperty = $reflection->getProperty('sets');
            $factorySets = $setsProperty->getValue($this->iconFactory);

            foreach ($factorySets as $setName => $setConfig) {
                $sets[$setName] = [
                    'name' => $setName,
                    'prefix' => $setConfig['prefix'] ?? $setName,
                    'path' => $setConfig['path'] ?? $setConfig['paths'][0] ?? null,
                    'paths' => $setConfig['paths'] ?? [$setConfig['path'] ?? null],
                ];
            }
        }

        return $sets;
    }

    /**
     * Get available icon set names.
     *
     * @return array<string>
     * @throws ReflectionException
     */
    public function getSetNames(): array
    {
        return array_keys($this->getSets());
    }

    /**
     * Get all icons from all allowed sets.
     *
     * @return Collection<int, array{name: string, set: string, prefix: string}>
     */
    public function getIcons(): Collection
    {
        $cacheKey = 'cloudcake:icons';

        return Cache::remember($cacheKey, 86400, fn() => $this->loadIcons());
    }

    /**
     * Load icons from disk.
     *
     * @return Collection<int, array{name: string, set: string, prefix: string}>
     * @throws ReflectionException
     */
    protected function loadIcons(): Collection
    {
        $icons = collect();
        $sets = $this->getSets();

        foreach ($sets as $setName => $setConfig) {
            $setIcons = $this->getIconsFromSet($setName, $setConfig);
            $icons = $icons->merge($setIcons);
        }

        return $icons->sortBy('name')->values();
    }

    /**
     * Get icons from a specific set.
     *
     * @param array<string, mixed> $setConfig
     * @return Collection<int, array{name: string, set: string, prefix: string}>
     */
    protected function getIconsFromSet(string $setName, array $setConfig): Collection
    {
        $icons = collect();
        $prefix = $setConfig['prefix'] ?? $setName;
        $paths = $setConfig['paths'] ?? [$setConfig['path'] ?? null];

        foreach ($paths as $path) {
            if (!$path || !File::isDirectory($path)) {
                continue;
            }

            $files = File::allFiles($path);

            foreach ($files as $file) {
                if ($file->getExtension() !== 'svg') {
                    continue;
                }

                $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $iconName = str_replace([DIRECTORY_SEPARATOR, '/', '.svg'], ['-', '-', ''], $relativePath);
                $fullName = $prefix . '-' . $iconName;

                $icons->push([
                    'name' => $fullName,
                    'set' => $setName,
                    'prefix' => $prefix,
                    'label' => $this->formatIconLabel($iconName),
                ]);
            }
        }

        return $icons;
    }

    /**
     * Format icon name to a human-readable label.
     */
    protected function formatIconLabel(string $name): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $name));
    }

    /**
     * Search icons by name.
     *
     * @param array<string>|null $allowedSets
     * @return Collection<int, array{name: string, set: string, prefix: string}>
     */
    public function searchIcons(string $query, ?array $allowedSets = null, ?string $setFilter = null): Collection
    {
        $icons = $this->filterByAllowedSets($this->getIcons(), $allowedSets);

        if ($setFilter) {
            $icons = $icons->filter(fn($icon) => $icon['set'] === $setFilter);
        }

        if (empty($query)) {
            return $icons;
        }

        $query = strtolower($query);

        return $icons->filter(function ($icon) use ($query) {
            return str_contains(strtolower($icon['name']), $query) ||
                str_contains(strtolower($icon['label']), $query);
        })->values();
    }

    /**
     * Get icons paginated for lazy loading.
     *
     * @param array<string>|null $allowedSets
     * @return array{icons: Collection, hasMore: bool, total: int}
     * @throws ReflectionException
     */
    public function getIconsPaginated(
        int     $page = 1,
        int     $perPage = 100,
        ?string $search = null,
        ?string $setFilter = null,
        ?array  $allowedSets = null
    ): array
    {
        $icons = $search
            ? $this->searchIcons($search, $allowedSets, $setFilter)
            : $this->filterByAllowedSets($this->getIcons(), $allowedSets);

        if ($setFilter && !$search) {
            $icons = $icons->filter(fn($icon) => $icon['set'] === $setFilter);
        }

        $total = $icons->count();
        $offset = ($page - 1) * $perPage;
        $paginatedIcons = $icons->slice($offset, $perPage)->values();

        return [
            'icons' => $paginatedIcons,
            'hasMore' => ($offset + $perPage) < $total,
            'total' => $total,
        ];
    }

    /**
     * Restrict a collection of icons to the given sets.
     *
     * @param Collection<int, array{name: string, set: string, prefix: string}> $icons
     * @param array<string>|null $allowedSets
     * @return Collection<int, array{name: string, set: string, prefix: string}>
     */
    protected function filterByAllowedSets(Collection $icons, ?array $allowedSets): Collection
    {
        if (empty($allowedSets)) {
            return $icons;
        }

        return $icons->filter(fn ($icon) => in_array($icon['set'], $allowedSets, true))->values();
    }

    /**
     * Clear the icon cache.
     */
    public function clearCache(): void
    {
        Cache::forget('cloudcake:icons');
    }
}
