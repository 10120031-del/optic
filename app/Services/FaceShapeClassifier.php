<?php

namespace App\Services;

class FaceShapeClassifier
{
    /**
     * Turns the millimetre measurements produced by the in-browser MediaPipe
     * scan (resources/js/face-scan.js) into one of the six face_shapes slugs
     * seeded by FaceShapeSeeder.
     *
     * The rules live here rather than in the browser on purpose: they're the
     * part most likely to need tuning against real customer photos, and
     * keeping them server-side means they can be adjusted (and unit tested)
     * without a front-end rebuild, and can't be tampered with by the client.
     *
     * Everything is expressed as a *ratio*, so the absolute mm scale doesn't
     * affect the classification — the scale matters for frame sizing, not for
     * shape.
     */
    public function classify(array $m): string
    {
        $cheek = (float) $m['cheekbone_width_mm'];

        $lengthRatio = (float) $m['face_length_mm'] / $cheek;
        $jawRatio = (float) $m['jaw_width_mm'] / $cheek;
        $foreheadRatio = (float) $m['forehead_width_mm'] / $cheek;

        return match (true) {
            // Noticeably longer than it is wide — this dominates, because a
            // long face reads as oblong whatever the jaw is doing.
            $lengthRatio > 1.50 => 'oblong',

            // Short face with a jaw nearly as wide as the cheekbones. Square
            // is the more angular of the two, so it takes the wider jaw.
            $jawRatio > 0.92 && $lengthRatio < 1.32 => 'square',
            $jawRatio > 0.85 && $lengthRatio < 1.25 => 'round',

            // Wide forehead tapering to a narrow chin.
            $foreheadRatio > 0.95 && $jawRatio < 0.80 => 'heart',

            // Cheekbones clearly the widest point, both ends narrow.
            $foreheadRatio < 0.85 && $jawRatio < 0.85 => 'diamond',

            default => 'oval',
        };
    }

    /**
     * Server-side sanity bounds for the client-supplied measurements.
     *
     * These come from a browser, so they're untrusted input: anyone can POST
     * arbitrary numbers. The ranges below are deliberately generous — wide
     * enough to cover any real adult face, narrow enough to reject noise,
     * a mis-detected face, or hand-edited values that would otherwise poison
     * the frame-sizing query.
     */
    public static function rules(): array
    {
        return [
            'face_length_mm' => ['required', 'numeric', 'between:90,260'],
            'cheekbone_width_mm' => ['required', 'numeric', 'between:80,200'],
            'jaw_width_mm' => ['required', 'numeric', 'between:60,200'],
            'forehead_width_mm' => ['required', 'numeric', 'between:60,200'],
            'pd_mm' => ['required', 'numeric', 'between:45,85'],
            'pd_left_mm' => ['required', 'numeric', 'between:20,45'],
            'pd_right_mm' => ['required', 'numeric', 'between:20,45'],
            'yaw_ratio' => ['required', 'numeric', 'between:0.82,1.22'],
        ];
    }
}
