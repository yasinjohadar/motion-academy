<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Permissions;
use Illuminate\Routing\Controllers\Middleware;

trait AuthorizesPanelResource
{
    /**
     * Build panel CRUD permission middleware for Laravel HasMiddleware.
     *
     * @param  array<string, array<int, string>>  $extra  permission name or crud key => method names
     * @param  array<int, string>  $listMethods
     * @return array<int, Middleware>
     */
    protected static function panelResourceMiddleware(
        string $panel,
        string $resource,
        bool $withShow = false,
        array $extra = [],
        array $listMethods = ['index'],
    ): array {
        $crud = Permissions::crud($panel, $resource);

        $listPerms = [
            $crud['list'],
            $crud['create'],
            $crud['edit'],
            $crud['delete'],
        ];

        if ($withShow) {
            $listPerms[] = $crud['show'];
            $listMethods = array_values(array_unique([...$listMethods, 'show']));
        }

        $middleware = [
            (new Middleware('permission:'.implode('|', $listPerms)))->only($listMethods),
            (new Middleware('permission:'.$crud['create']))->only(['create', 'store']),
        ];

        $editMethods = ['edit', 'update'];
        if (isset($extra['edit'])) {
            $editMethods = array_merge($editMethods, $extra['edit']);
            unset($extra['edit']);
        }
        $middleware[] = (new Middleware('permission:'.$crud['edit']))->only($editMethods);
        $middleware[] = (new Middleware('permission:'.$crud['delete']))->only(['destroy']);

        if ($withShow) {
            $showMethods = ['show'];
            if (isset($extra['show'])) {
                $showMethods = array_merge($showMethods, $extra['show']);
                unset($extra['show']);
            }
            $middleware[] = (new Middleware('permission:'.$crud['show']))->only($showMethods);
        }

        foreach ($extra as $permission => $methods) {
            $middleware[] = (new Middleware('permission:'.$permission))->only($methods);
        }

        return $middleware;
    }
}
