<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminTable
{
    /**
     * Apply an allow-listed sort to an admin listing.
     *
     * @param  array<string, string|callable>  $columns
     * @param  array<int, array{0: string, 1: string}>  $default
     */
    public static function sort(
        Builder $query,
        Request $request,
        array $columns,
        array $default,
        string $sortKey = 'sort',
        string $directionKey = 'direction',
    ): Builder {
        $sort = $request->string($sortKey)->toString();
        $direction = $request->string($directionKey)->lower()->toString() === 'asc' ? 'asc' : 'desc';

        if ($sort !== '' && array_key_exists($sort, $columns)) {
            $column = $columns[$sort];
            if (is_callable($column)) {
                $column($query, $direction);
            } else {
                $query->orderBy($column, $direction);
            }

            return $query;
        }

        foreach ($default as [$column, $defaultDirection]) {
            $query->orderBy($column, $defaultDirection);
        }

        return $query;
    }

    public static function term(Request $request, string $key = 'q'): string
    {
        return mb_substr(trim($request->string($key)->toString()), 0, 100);
    }
}
