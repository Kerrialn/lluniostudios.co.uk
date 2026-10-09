<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The first name for "Dear {first name}," (Greeting name, decided 9 October
 * 2026): titles (Mr, Mrs, Ms, Dr…) and initials are skipped and capitals are
 * fixed ("MRS J. SMITH" → "Smith"? no: the first usable word, here "Smith";
 * "ada lovelace" → "Ada"). Null when no usable first name is left (the
 * email then says "Dear Customer,").
 */
final class GreetingName
{
    private const TITLES = ['mr', 'mrs', 'ms', 'miss', 'mx', 'master', 'dr', 'prof', 'professor', 'sir', 'dame', 'lady', 'lord', 'rev', 'revd', 'reverend', 'fr', 'father', 'capt', 'captain', 'col', 'major', 'lt', 'hon', 'the'];

    public static function firstName(?string $fullName): ?string
    {
        foreach (preg_split('/\s+/u', trim((string) $fullName)) ?: [] as $word) {
            $bare = rtrim($word, '.,');
            // Titles and initials ("J", "J.", "J.R.") are skipped.
            $initials = mb_strlen($bare) === 1 || preg_match('/^(\p{L}\.)+\p{L}?$/u', $bare) === 1;
            if ($bare === '' || $initials || \in_array(mb_strtolower($bare), self::TITLES, true)) {
                continue;
            }

            // Only letters (with hyphens or apostrophes inside) make a name.
            if (preg_match("/^\\p{L}+(?:['’-]\\p{L}+)*$/u", $bare) !== 1) {
                continue;
            }

            return self::capitalise($bare);
        }

        return null;
    }

    /**
     * "ADA" or "ada" → "Ada", "mary-jane" → "Mary-Jane", "éLODIE" → "Élodie";
     * a name typed with a capital first and mixed capitals after ("DeShawn")
     * is kept as typed.
     */
    private static function capitalise(string $name): string
    {
        // Typed with a capital first and mixed capitals after ("DeShawn"): as typed.
        $first = mb_substr($name, 0, 1);
        $mixed = $name !== mb_strtolower($name) && $name !== mb_strtoupper($name);
        if ($mixed && $first === mb_strtoupper($first)) {
            return $name;
        }

        return (string) preg_replace_callback("/(^|['’-])(\\p{L})(\\p{L}*)/u", static fn (array $m): string => $m[1] . mb_strtoupper($m[2]) . mb_strtolower($m[3]), $name);
    }
}
