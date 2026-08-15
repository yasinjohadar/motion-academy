<?php

namespace App\Support;

class Permissions
{
    /**
     * @return array<string, mixed>
     */
    public static function panels(): array
    {
        return config('permissions.panels', []);
    }

    /**
     * @return array<int, string>
     */
    public static function all(?string $panel = null): array
    {
        $names = [];

        foreach (self::panelConfigs($panel) as $panelConfig) {
            foreach ($panelConfig['groups'] ?? [] as $group) {
                foreach ($group['permissions'] ?? [] as $name => $label) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(?string $panel = null): array
    {
        $grouped = [];

        foreach (self::panelConfigs($panel) as $panelKey => $panelConfig) {
            $panelLabel = $panelConfig['label'] ?? $panelKey;

            foreach ($panelConfig['groups'] ?? [] as $groupKey => $group) {
                $label = ($panel === null ? "{$panelLabel} — " : '')
                    .($group['label'] ?? $groupKey);
                $grouped[$label] = $group['permissions'] ?? [];
            }
        }

        return $grouped;
    }

    /**
     * @return array<int, string>
     */
    public static function forRole(string $role): array
    {
        foreach (self::panels() as $panelKey => $panelConfig) {
            if (! array_key_exists($role, $panelConfig['roles'] ?? [])) {
                continue;
            }

            $package = $panelConfig['roles'][$role];

            if ($package === ['*'] || $package === '*') {
                return self::all($panelKey);
            }

            return array_values($package);
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(?string $panel = null): array
    {
        $labels = [];

        foreach (self::panelConfigs($panel) as $panelConfig) {
            foreach ($panelConfig['groups'] ?? [] as $group) {
                foreach ($group['permissions'] ?? [] as $name => $label) {
                    $labels[$name] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * Canonical permission names for a resource.
     *
     * `editAny`/`deleteAny` cover acting on records created by someone else; they are
     * only registered in config for resources that actually track an owner, and are
     * consumed by App\Policies\BasePolicy.
     *
     * @return array{list: string, create: string, edit: string, delete: string, show: string, editAny: string, deleteAny: string}
     */
    public static function crud(string $panel, string $resource): array
    {
        return [
            'list' => "{$panel}.{$resource}.list",
            'create' => "{$panel}.{$resource}.create",
            'edit' => "{$panel}.{$resource}.edit",
            'delete' => "{$panel}.{$resource}.delete",
            'show' => "{$panel}.{$resource}.show",
            'editAny' => "{$panel}.{$resource}.edit-any",
            'deleteAny' => "{$panel}.{$resource}.delete-any",
        ];
    }

    public static function panelOf(string $permission): ?string
    {
        $parts = explode('.', $permission, 2);

        return $parts[0] !== '' ? $parts[0] : null;
    }

    /**
     * @param  iterable<int|string, mixed>|null  $existingNames
     * @return array<int, array{key: string, label: string, permissions: array<int, array{name: string, label: string}>}>
     */
    public static function groupedForPicker(?string $panel = null, ?iterable $existingNames = null): array
    {
        $existing = null;

        if ($existingNames !== null) {
            $existing = [];
            foreach ($existingNames as $name) {
                $existing[(string) $name] = true;
            }
        }

        $groups = [];
        $known = [];

        foreach (self::panelConfigs($panel) as $panelKey => $panelConfig) {
            foreach ($panelConfig['groups'] ?? [] as $key => $group) {
                $permissions = [];

                foreach ($group['permissions'] ?? [] as $name => $label) {
                    if ($existing !== null && ! isset($existing[$name])) {
                        continue;
                    }

                    $permissions[] = ['name' => $name, 'label' => $label];
                    $known[$name] = true;
                }

                if ($permissions === []) {
                    continue;
                }

                $groupKey = $panel === null ? "{$panelKey}.{$key}" : $key;
                $groupLabel = $panel === null
                    ? (($panelConfig['label'] ?? $panelKey).' — '.($group['label'] ?? $key))
                    : ($group['label'] ?? $key);

                $groups[] = [
                    'key' => $groupKey,
                    'label' => $groupLabel,
                    'permissions' => $permissions,
                ];
            }
        }

        if ($existing !== null) {
            $orphans = [];

            foreach (array_keys($existing) as $name) {
                if (isset($known[$name])) {
                    continue;
                }

                if ($panel !== null && self::panelOf($name) !== $panel) {
                    continue;
                }

                $orphans[] = ['name' => $name, 'label' => $name];
            }

            if ($orphans !== []) {
                usort($orphans, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

                $groups[] = [
                    'key' => 'other',
                    'label' => 'صلاحيات أخرى',
                    'permissions' => $orphans,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function panelConfigs(?string $panel): array
    {
        $panels = self::panels();

        if ($panel === null) {
            return $panels;
        }

        return isset($panels[$panel]) ? [$panel => $panels[$panel]] : [];
    }
}
