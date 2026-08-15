<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Adds per-record authorization flags to a model's admin payload.
 *
 * Without these, a list renders edit and delete buttons that throw 403 on click:
 * the frontend only knows type-level permissions from the shared `auth.permissions`
 * prop, which cannot express "you may edit this row but not that one".
 *
 * The checks are pure in-memory Gate calls — spatie caches the user's permissions for
 * the request, so a page of rows costs no extra queries. PolicyFlagsTest asserts that.
 */
trait ExposesPolicyFlags
{
    /**
     * @param  array<int, string>  $abilities
     * @return array<string, bool>
     */
    public function policyFlags(array $abilities = ['update', 'delete']): array
    {
        if (! auth()->check()) {
            return array_fill_keys($abilities, false);
        }

        $flags = [];

        foreach ($abilities as $ability) {
            $flags[$ability] = Gate::allows($ability, $this);
        }

        return $flags;
    }
}
