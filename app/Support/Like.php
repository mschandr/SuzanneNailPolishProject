<?php

namespace App\Support;

class Like
{
    /**
     * A "contains" pattern for use with `like ? escape '!'`. Wildcards in the term are escaped so they match
     * literally. "!" is the escape character because a backslash inside SQL breaks PDO's Postgres parsing.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
    }
}
