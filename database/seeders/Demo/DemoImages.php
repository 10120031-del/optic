<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Storage;

/**
 * The photos on the public disk that are usable as product shots.
 *
 * The demo attaches real uploads to frames and to review bodies rather than
 * inventing paths, because a row pointing at a file that is not there renders
 * a broken image, while no row at all renders the placeholder the frame-card
 * component was designed around. So this only ever returns files that exist.
 *
 * The landscape test is the useful part. `storage/app/public/frames` collects
 * everything staff have ever uploaded through the admin screens, and that is
 * not all product photography — logos, icons and other square graphics end up
 * there too, and one of those stretched across forty product cards is worse
 * than no photo at all. Eyewear is photographed wide, so requiring width to
 * exceed height keeps the shots and drops the icons without anyone having to
 * maintain a list of filenames.
 *
 * Read from disk once per process: this is called for every frame and for a
 * fifth of the reviews, and getimagesize() opens the file each time.
 */
final class DemoImages
{
    /** @var array<int, string>|null */
    private static ?array $cache = null;

    /**
     * @return array<int, string> Paths relative to the public disk.
     */
    public static function productShots(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $disk = Storage::disk('public');

        return self::$cache = collect($disk->files('frames'))
            ->filter(fn (string $path) => in_array(
                strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                ['jpg', 'jpeg', 'png', 'webp'],
                true
            ))
            ->filter(function (string $path) use ($disk) {
                $size = @getimagesize($disk->path($path));

                return $size !== false && $size[0] > $size[1];
            })
            ->values()
            ->all();
    }
}
