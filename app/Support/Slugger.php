<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slugger
{
    public static function from(string $value, string $fallback = 'item'): string
    {
        $slug = Str::slug(Str::ascii($value));

        if ($slug === '') {
            $slug = $fallback.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $scope
     */
    public static function unique(string $value, string $modelClass, array $scope = [], ?int $ignoreId = null, string $fallback = 'item'): string
    {
        $base = self::from($value, $fallback);
        $slug = $base;
        $i = 2;

        while (
            $modelClass::query()
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->when($scope !== [], fn ($query) => $query->where($scope))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
