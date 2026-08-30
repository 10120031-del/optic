<?php

namespace Tests\Feature;

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Prescription scans are medical records: they sit on the private disk and
 * are only ever handed out by the controller, which checks owner-or-staff.
 */
class PrescriptionFileAccessTest extends TestCase
{
    use RefreshDatabase;

    private function prescriptionFor(User $user): Prescription
    {
        Storage::fake('local');

        return $user->prescriptions()->create([
            'file_path' => UploadedFile::fake()->create('rx.pdf', 12, 'application/pdf')
                ->store('prescriptions', 'local'),
            'doctor_name' => 'Dr. Reyes',
        ]);
    }

    public function test_owner_can_open_their_own_scan(): void
    {
        $user = User::factory()->create();
        $prescription = $this->prescriptionFor($user);

        $this->actingAs($user)
            ->get(route('prescriptions.file', $prescription))
            ->assertOk();
    }

    public function test_staff_can_open_a_customer_scan_to_verify_it(): void
    {
        $customer = User::factory()->create();
        $prescription = $this->prescriptionFor($customer);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('prescriptions.file', $prescription))
            ->assertOk();
    }

    public function test_another_customer_gets_a_404_rather_than_the_file(): void
    {
        $prescription = $this->prescriptionFor(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('prescriptions.file', $prescription))
            ->assertNotFound();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $prescription = $this->prescriptionFor(User::factory()->create());

        $this->get(route('prescriptions.file', $prescription))
            ->assertRedirect(route('login'));
    }

    public function test_a_prescription_with_no_upload_has_nothing_to_serve(): void
    {
        $user = User::factory()->create();
        $prescription = $user->prescriptions()->create(['doctor_name' => 'Dr. Reyes']);

        $this->actingAs($user)
            ->get(route('prescriptions.file', $prescription))
            ->assertNotFound();
    }

    public function test_scans_are_not_reachable_through_the_public_storage_route(): void
    {
        $user = User::factory()->create();
        $prescription = $this->prescriptionFor($user);

        // Laravel's built-in /storage/{path} route only serves the private
        // disk against a valid signature — an unsigned guess must not work,
        // even for the file's own owner.
        $this->actingAs($user)
            ->get('/storage/'.$prescription->file_path)
            ->assertForbidden();
    }
}
