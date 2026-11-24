<?php

namespace App\Support;

/**
 * Name typed on Direct Online Payment, compared with the stored account name.
 *
 * The typed name is consistent with the stored name when it contains the
 * surname, in any order. The first name may be left out. Middle names,
 * initials, and generational suffixes may be left out too. A different given
 * name, a different surname, or the wrong suffix is not consistent.
 */
class AccountName
{
    private const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv', 'vi', 'vii', 'viii', 'ix'];

    public function __construct(private readonly string $value)
    {
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function isConsistentWith(string $databaseName): bool
    {
        $form = self::parse($this->value);
        $database = self::parse($databaseName);

        if ($form['compact'] === '' || $database['compact'] === '') {
            return false;
        }

        if ($form['compact'] === $database['compact']) {
            return true;
        }

        if ($database['surname'] === [] && $database['given'] === []) {
            return false;
        }

        if (!self::suffixesFit($form['suffixes'], $database['suffixes'])) {
            return false;
        }

        return self::search(
            $form['sequence'],
            0,
            $database,
            array_fill(0, count($database['surname']), false),
            array_fill(0, count($database['given']), false),
            false
        );
    }

    /**
     * @return array{
     *     compact: string,
     *     hasComma: bool,
     *     surname: list<array{0: string, 1: bool}>,
     *     given: list<array{0: string, 1: bool}>,
     *     suffixes: list<string>,
     *     sequence: list<array{0: string, 1: bool}>,
     *     protectedGiven: ?int
     * }
     */
    private static function parse(string $name): array
    {
        $folded = self::fold($name);
        $compact = preg_replace('/[^a-z0-9]/', '', $folded) ?? '';
        $pieces = preg_split('/,/', $folded, 2) ?: [];
        $hasComma = count($pieces) === 2;

        $suffixes = [];
        $surname = self::tokensIn($pieces[0] ?? '', $suffixes);
        $given = $hasComma ? self::tokensIn($pieces[1] ?? '', $suffixes) : [];

        if (!$hasComma) {
            $surname = array_merge($surname, $given);
            $given = [];
        }

        if ($hasComma && count($surname) > 1 && in_array($surname[array_key_last($surname)][0], ['i', 'v'], true)) {
            $suffixes[] = $surname[array_key_last($surname)][0];
            array_pop($surname);
            if ($surname !== []) {
                $surname[array_key_last($surname)][1] = false;
            }
        }

        $protectedGiven = null;
        foreach ($given as $index => $token) {
            if (strlen($token[0]) > 1) {
                $protectedGiven = $index;
                break;
            }
        }

        return [
            'compact' => $compact,
            'hasComma' => $hasComma,
            'surname' => $surname,
            'given' => $given,
            'suffixes' => $suffixes,
            'sequence' => array_merge($surname, $given),
            'protectedGiven' => $protectedGiven,
        ];
    }

    private static function fold(string $name): string
    {
        $folded = mb_strtolower(trim($name));
        $folded = strtr($folded, [
            'ñ' => 'n',
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        ]);
        $folded = preg_replace('/\bde\s+la\b/u', 'dela', $folded) ?? $folded;
        $folded = preg_replace('/\bde\s+los\b/u', 'delos', $folded) ?? $folded;
        $folded = preg_replace('/\bde\s+las\b/u', 'delas', $folded) ?? $folded;
        $folded = preg_replace('/\bsta\.?\b/u', 'santa', $folded) ?? $folded;
        $folded = preg_replace('/\bsto\.?\b/u', 'santo', $folded) ?? $folded;

        return $folded;
    }

    /**
     * Words inside one comma-separated part. A suffix breaks the run of words
     * so "de leon" can join, while "albert jr s" cannot become "alberts".
     *
     * @param list<string> $suffixes
     * @return list<array{0: string, 1: bool}>
     */
    private static function tokensIn(string $text, array &$suffixes): array
    {
        $tokens = [];
        $buffer = [];
        $flush = static function () use (&$buffer, &$tokens): void {
            $count = count($buffer);
            foreach ($buffer as $index => $token) {
                $tokens[] = [$token, $index < $count - 1];
            }
            $buffer = [];
        };

        foreach (self::tokenize($text) as $token) {
            if (in_array($token, self::SUFFIXES, true)) {
                $flush();
                $suffixes[] = $token;
                continue;
            }
            $buffer[] = $token;
        }
        $flush();

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ?: [];
    }

    /**
     * @param list<string> $form
     * @param list<string> $database
     */
    private static function suffixesFit(array $form, array $database): bool
    {
        $available = array_count_values($database);
        foreach ($form as $suffix) {
            if (($available[$suffix] ?? 0) < 1) {
                return false;
            }
            $available[$suffix]--;
        }

        return true;
    }

    /**
     * @param list<array{0: string, 1: bool}> $sequence
     * @param array{hasComma: bool, surname: list<array{0: string, 1: bool}>, given: list<array{0: string, 1: bool}>, protectedGiven: ?int} $database
     * @param list<bool> $usedSurname
     * @param list<bool> $usedGiven
     */
    private static function search(array $sequence, int $index, array $database, array $usedSurname, array $usedGiven, bool $requiresGiven): bool
    {
        if ($index >= count($sequence)) {
            return self::covered($database, $usedSurname, $usedGiven, $requiresGiven);
        }

        $token = $sequence[$index][0];
        if (strlen($token) === 1) {
            if (self::search($sequence, $index + 1, $database, $usedSurname, $usedGiven, true)) {
                return true;
            }
            foreach (self::matchInitial($token, $database, $usedSurname, $usedGiven) as $next) {
                if (self::search($sequence, $index + 1, $database, $next[0], $next[1], $requiresGiven || $next[1] !== $usedGiven)) {
                    return true;
                }
            }

            return false;
        }

        $joined = '';
        for ($end = $index; $end < count($sequence); $end++) {
            if (strlen($sequence[$end][0]) === 1) {
                break;
            }
            $joined .= $sequence[$end][0];
            foreach (self::matchPhrase($joined, $database, $usedSurname, $usedGiven) as $next) {
                if (self::search($sequence, $end + 1, $database, $next[0], $next[1], $requiresGiven || $next[1] !== $usedGiven)) {
                    return true;
                }
            }
            if (!$sequence[$end][1]) {
                break;
            }
        }

        return false;
    }

    /**
     * @param array{hasComma: bool, surname: list<array{0: string, 1: bool}>, given: list<array{0: string, 1: bool}>, protectedGiven: ?int} $database
     * @param list<bool> $usedSurname
     * @param list<bool> $usedGiven
     */
    private static function covered(array $database, array $usedSurname, array $usedGiven, bool $requiresGiven): bool
    {
        foreach ($database['surname'] as $index => $token) {
            if ($usedSurname[$index]) {
                continue;
            }
            if (!$database['hasComma'] && strlen($token[0]) === 1) {
                continue;
            }

            return false;
        }

        $protected = $database['protectedGiven'];
        if ($requiresGiven && $protected !== null && !$usedGiven[$protected]) {
            return false;
        }

        return true;
    }

    /**
     * @param array{surname: list<array{0: string, 1: bool}>, given: list<array{0: string, 1: bool}>, protectedGiven: ?int} $database
     * @param list<bool> $usedSurname
     * @param list<bool> $usedGiven
     * @return list<array{0: list<bool>, 1: list<bool>}>
     */
    private static function matchInitial(string $letter, array $database, array $usedSurname, array $usedGiven): array
    {
        $options = [];
        foreach ($database['surname'] as $index => $token) {
            if (!$usedSurname[$index] && $token[0] === $letter) {
                $next = $usedSurname;
                $next[$index] = true;
                $options[] = [$next, $usedGiven];
            }
        }

        foreach ($database['given'] as $index => $token) {
            if ($usedGiven[$index] || $index === $database['protectedGiven']) {
                continue;
            }
            $exact = $token[0] === $letter;
            $prefix = strlen($token[0]) > 1 && str_starts_with($token[0], $letter);
            if ($exact || $prefix) {
                $next = $usedGiven;
                $next[$index] = true;
                $options[] = [$usedSurname, $next];
            }
        }

        return $options;
    }

    /**
     * @param array{surname: list<array{0: string, 1: bool}>, given: list<array{0: string, 1: bool}>} $database
     * @param list<bool> $usedSurname
     * @param list<bool> $usedGiven
     * @return list<array{0: list<bool>, 1: list<bool>}>
     */
    private static function matchPhrase(string $phrase, array $database, array $usedSurname, array $usedGiven): array
    {
        $options = [];
        self::collectPhraseMatches($phrase, $database['surname'], $usedSurname, $usedGiven, true, $options);
        self::collectPhraseMatches($phrase, $database['given'], $usedGiven, $usedSurname, false, $options);

        return $options;
    }

    /**
     * @param list<array{0: string, 1: bool}> $pool
     * @param list<bool> $usedPool
     * @param list<bool> $otherUsed
     * @param list<array{0: list<bool>, 1: list<bool>}> $options
     */
    private static function collectPhraseMatches(string $phrase, array $pool, array $usedPool, array $otherUsed, bool $poolIsSurname, array &$options): void
    {
        $count = count($pool);
        for ($start = 0; $start < $count; $start++) {
            if ($usedPool[$start]) {
                continue;
            }

            $joined = '';
            for ($end = $start; $end < $count; $end++) {
                if ($usedPool[$end]) {
                    break;
                }

                $joined .= $pool[$end][0];
                if ($joined === $phrase) {
                    $nextPool = $usedPool;
                    for ($mark = $start; $mark <= $end; $mark++) {
                        $nextPool[$mark] = true;
                    }
                    $options[] = $poolIsSurname ? [$nextPool, $otherUsed] : [$otherUsed, $nextPool];
                    break;
                }

                if (!$pool[$end][1] || !str_starts_with($phrase, $joined)) {
                    break;
                }
            }
        }
    }
}
