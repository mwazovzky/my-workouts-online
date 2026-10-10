<?php

namespace App\Http;

use App\Models\Workout;

/**
 * The workout's entity tag, `"<id>-<version>"`, and the RFC 9110 `If-Match`
 * test against it. Comparison is strong: a weak tag (`W/"…"`) never matches.
 */
final class WorkoutETag
{
    public static function for(Workout $workout): string
    {
        return sprintf('"%d-%d"', $workout->id, $workout->version);
    }

    /**
     * Whether an `If-Match` field value (`*` or a comma-separated tag list) matches the workout.
     */
    public static function matches(string $ifMatch, Workout $workout): bool
    {
        $ifMatch = trim($ifMatch);

        if ($ifMatch === '*') {
            return true;
        }

        $current = self::for($workout);

        foreach (explode(',', $ifMatch) as $tag) {
            if (trim($tag) === $current) {
                return true;
            }
        }

        return false;
    }
}
