<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $prescriptions = $request->user()->prescriptions()->latest()->get();

        return view('prescriptions.index', ['prescriptions' => $prescriptions]);
    }

    public function create(): View
    {
        return view('prescriptions.create');
    }

    /**
     * Save a prescription on file: the numbers used to grind lenses, plus
     * an optional photo/PDF of the physical prescription as the paper
     * trail. Neither expires_at nor is_verified is user-settable — staff
     * verify uploads separately (see Admin\PrescriptionController, phase 4).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'clinic_name' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'left_sphere' => ['nullable', 'numeric'],
            'left_cylinder' => ['nullable', 'numeric'],
            'left_axis' => ['nullable', 'integer', 'between:0,180'],
            'left_add' => ['nullable', 'numeric'],
            'right_sphere' => ['nullable', 'numeric'],
            'right_cylinder' => ['nullable', 'numeric'],
            'right_axis' => ['nullable', 'integer', 'between:0,180'],
            'right_add' => ['nullable', 'numeric'],
            'pd' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('prescriptions', 'local');
        }

        $request->user()->prescriptions()->create($data);

        return redirect()->route('prescriptions.index')->with('status', 'Prescription saved.');
    }

    /**
     * Stream a prescription's uploaded scan.
     *
     * The file lives on the private disk (storage/app/private), so it is not
     * reachable through the public/storage symlink and Laravel's built-in
     * /storage/{path} route rejects it without a signature. Serving it here
     * instead keeps the authorisation explicit: the owner, or staff who need
     * it to verify the numbers. A stranger gets a 404 rather than a 403 so
     * the URL space doesn't confirm which prescription ids exist.
     */
    public function file(Request $request, Prescription $prescription): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $prescription->file_path && ($prescription->user_id === $user->id || $user->canAccessAdminConsole()),
            404
        );

        return Storage::disk('local')->response($prescription->file_path, null, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function destroy(Request $request, Prescription $prescription): RedirectResponse
    {
        abort_unless($prescription->user_id === $request->user()->id, 403);

        if ($prescription->file_path) {
            Storage::disk('local')->delete($prescription->file_path);
        }

        $prescription->delete();

        return back()->with('status', 'Prescription removed.');
    }
}
