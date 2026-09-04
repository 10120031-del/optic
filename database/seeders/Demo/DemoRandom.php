<?php

namespace Database\Seeders\Demo;

use Carbon\CarbonImmutable;

/**
 * Deterministic randomness for the demo fixtures.
 *
 * Everything here draws from PHP's Mt19937 engine, seeded once by
 * DemoSeeder. That is the whole point: the same seed rebuilds byte-for-byte
 * the same shop, so a demo rehearsed on Monday still shows the same best
 * seller on Friday, and a screenshot in a slide deck still matches the
 * running site.
 *
 * Faker would have been the obvious tool, but it is a require-dev package —
 * a server installed with `composer install --no-dev` would not have it, and
 * this seeder is meant to run exactly there.
 */
final class DemoRandom
{
    public static function seed(int $seed = DemoConfig::SEED): void
    {
        mt_srand($seed);
    }

    public static function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    public static function pick(array $items)
    {
        $items = array_values($items);

        return $items[mt_rand(0, count($items) - 1)];
    }

    /**
     * A distinct draw of $count items.
     *
     * Shuffles a copy of the keys by hand rather than calling shuffle(), so
     * the draw depends only on the sequence of mt_rand() calls made here and
     * stays stable across PHP versions that reimplement shuffle().
     *
     * @param  array<int, mixed>  $items
     * @return array<int, mixed>
     */
    public static function pickMany(array $items, int $count): array
    {
        $items = array_values($items);
        $count = max(0, min($count, count($items)));

        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return array_slice($items, 0, $count);
    }

    public static function chance(int $percent): bool
    {
        return mt_rand(1, 100) <= $percent;
    }

    /**
     * Pick a key from a map of key => relative weight.
     *
     * @param  array<string|int, int>  $weights
     */
    public static function weighted(array $weights): string|int
    {
        $roll = mt_rand(1, max(1, (int) array_sum($weights)));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }

    public static function float(float $min, float $max, int $decimals = 2): float
    {
        $factor = 10 ** $decimals;

        return mt_rand((int) round($min * $factor), (int) round($max * $factor)) / $factor;
    }

    /**
     * A value on a 0.25 grid — the only way optical powers are ever written.
     */
    public static function quarterStep(float $min, float $max): float
    {
        $steps = (int) round(($max - $min) / 0.25);

        return round($min + mt_rand(0, $steps) * 0.25, 2);
    }

    /**
     * A moment in the last $days, skewed towards the present.
     *
     * Taking the smaller of two draws bends a flat distribution into one that
     * thickens as it approaches today, which is what a growing shop's order
     * book actually looks like — and it makes the dashboard's revenue chart
     * trend upwards instead of sitting flat.
     */
    public static function recentMoment(int $days): CarbonImmutable
    {
        $a = mt_rand(0, $days);
        $b = mt_rand(0, $days);

        $now = CarbonImmutable::now();

        $moment = $now
            ->subDays(min($a, $b))
            ->setTime(mt_rand(8, 22), mt_rand(0, 59), mt_rand(0, 59));

        // Shop hours run to ten at night, so a draw on today's date can land
        // after the current clock time — an order placed this evening, seeded
        // this afternoon. Roll it back a day rather than clamping to now(),
        // which would pile every such draw onto one identical timestamp.
        return $moment->isFuture() ? $moment->subDay() : $moment;
    }
}
