<?php

namespace App\Http\Controllers;

use App\Models\FaceShape;
use App\Services\FaceShapeClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaceMatchController extends Controller
{
    /**
     * The face-scan AI page (requirement 3).
     *
     * The scan itself runs in the browser: resources/js/face-scan.js loads
     * MediaPipe's 478-point face landmarker (self-hosted under /mediapipe)
     * and derives millimetre measurements from the photo locally. Scale comes
     * from the iris, whose horizontal diameter is ~11.7mm across the adult
     * population — that constant is what converts pixels to millimetres with
     * no reference card and no known camera distance.
     *
     * The photo is never uploaded. analyze() receives only the numbers, which
     * keeps biometric image data off the server entirely and means there's no
     * face-scan retention policy to write.
     */
    public function create(): View
    {
        return view('face-match.create', ['faceShapes' => FaceShape::orderBy('name')->get()]);
    }

    /**
     * Receives the measurements from the in-browser scan, classifies them,
     * and hands back the URL of the matching recommendations page.
     *
     * The measurements are remembered in the session so recommend() can size
     * frames against this face, and so the PD can be offered as a prefill on
     * the eyeglass configurator later in the journey.
     */
    public function analyze(Request $request, FaceShapeClassifier $classifier): JsonResponse
    {
        $measurements = $request->validate(FaceShapeClassifier::rules());

        $slug = $classifier->classify($measurements);
        $faceShape = FaceShape::where('slug', $slug)->firstOrFail();

        $request->session()->put('face_scan', $measurements + ['shape' => $slug]);

        return response()->json([
            'redirect' => route('face-match.recommend', $faceShape),
        ]);
    }

    /**
     * Frames for a face shape, ordered by how well they physically fit when
     * we have a scan to go on.
     *
     * Two independent fit signals, both from columns the catalog already
     * carries:
     *
     *  - Frame PD (the distance between the two lenses' geometric centres) is
     *    lens_width + bridge_width. The gap between that and the wearer's own
     *    PD is exactly how far the lenses have to be decentered to line up
     *    with their pupils, so a small gap means less induced prism and
     *    thinner edges. It's weighted double because it's the measurement
     *    with real optical consequences.
     *  - Overall frame width against the width of the face, so the frame
     *    neither pinches nor overhangs.
     *
     * Reached directly (from the manual shape picker) there's no scan in the
     * session, and it falls back to the curated ordering.
     */
    public function recommend(Request $request, FaceShape $faceShape): View
    {
        $scan = $request->session()->get('face_scan');

        $frames = $faceShape->frames()
            ->where('is_active', true)
            ->with('primaryImage')
            ->withAvg('approvedReviews as reviews_avg_rating', 'rating')
            ->when($scan, fn ($query) => $query->orderByRaw(
                '(ABS((frames.lens_width + frames.bridge_width) - ?) * 2)
                 + ABS(COALESCE(frames.frame_width, (frames.lens_width * 2) + frames.bridge_width) - ?) ASC',
                [$scan['pd_mm'], $scan['cheekbone_width_mm']]
            ))
            ->paginate(24)
            ->withQueryString();

        return view('face-match.recommend', [
            'faceShape' => $faceShape,
            'frames' => $frames,
            'scan' => $scan,
        ]);
    }
}
