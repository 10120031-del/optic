<?php

namespace Database\Seeders\Demo;

use App\Models\Prescription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Prescriptions on file, in every state the shop actually has to deal with.
 *
 * There are four worth showing and this seeder produces all four: verified
 * and current (the happy path a customer picks at checkout), unverified and
 * waiting (what fills the staff console's verification queue), expired (the
 * storefront warns and will not let it be reused), and about to expire.
 *
 * The numbers are drawn on a 0.25 dioptre grid because that is the only way
 * powers are ever written, and the add power is only present where a lens
 * type would actually carry one. A demo where every prescription reads
 * -1.75 / -1.75 tells the audience nothing about the fields.
 *
 * file_path is left null throughout. The column holds an uploaded photo or
 * PDF of the paper prescription, and inventing a path to a file that is not
 * on the disk would give every row a download link that 404s.
 */
class DemoPrescriptionSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, string> */
    private const DOCTORS = [
        'Dr. Rania Chami', 'Dr. Georges Nassar', 'Dr. Samir Abou Khalil', 'Dr. Lina Hobeika',
        'Dr. Walid Traboulsi', 'Dr. Maya Sarkis', 'Dr. Fouad Merhi', 'Dr. Carine Azar',
    ];

    /** @var array<int, string> */
    private const CLINICS = [
        'Beirut Eye Specialist Hospital', 'Hotel-Dieu de France — Ophthalmology',
        'Clemenceau Medical Centre', 'Saint George Hospital Eye Clinic',
        'Mount Lebanon Eye Care', 'Sahel General — Vision Unit',
        'Tripoli Vision Clinic', 'Zahle Family Eye Care',
    ];

    /** @var array<int, string> */
    private const NOTES = [
        'Patient reports eye strain after long screen sessions; anti-blue-light recommended.',
        'Slight increase in the right eye since the last check.',
        'First pair of progressives — advise a two-week adaptation period.',
        'Prefers a smaller frame; keep the lens height above 38mm for the corridor.',
        'Contact lens wearer; spectacle backup required.',
        'Stable for two years running.',
    ];

    public function run(): void
    {
        $customers = User::where('role', 'customer')
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->orderBy('id')
            ->get();

        foreach ($customers as $customer) {
            // Not everybody who buys sunglasses has a prescription on file.
            if (! DemoRandom::chance(70)) {
                continue;
            }

            // A repeat customer has last year's record sitting behind this
            // year's, which is what makes the "expired" state reachable in
            // the UI without the current one disappearing.
            $count = DemoRandom::chance(25) ? 2 : 1;

            for ($i = 0; $i < $count; $i++) {
                $this->createFor($customer, olderRecord: $i > 0);
            }
        }
    }

    private function createFor(User $customer, bool $olderRecord): void
    {
        $issued = $olderRecord
            ? CarbonImmutable::now()->subMonths(DemoRandom::int(26, 40))
            : CarbonImmutable::now()->subMonths(DemoRandom::int(1, 22));

        // Prescriptions are written for two years here; the older record is
        // therefore always past its date, and roughly one current record in
        // six has quietly lapsed too.
        $expires = $issued->addMonths(24);

        // Myopia is far more common than hyperopia, and the two eyes are
        // usually close but rarely identical — a shared base with a small
        // per-eye offset reproduces both facts.
        $base = DemoRandom::chance(78)
            ? DemoRandom::quarterStep(-6.50, -0.50)
            : DemoRandom::quarterStep(0.50, 3.25);

        $rightSphere = round($base + DemoRandom::quarterStep(-0.50, 0.50), 2);
        $leftSphere = round($base + DemoRandom::quarterStep(-0.50, 0.50), 2);

        $hasAstigmatism = DemoRandom::chance(55);
        $needsAdd = $issued->diffInYears(CarbonImmutable::now()) < 3 && DemoRandom::chance(30);

        $verified = ! $olderRecord && DemoRandom::chance(72);

        $prescription = Prescription::create([
            'user_id' => $customer->id,
            'file_path' => null,
            'doctor_name' => DemoRandom::pick(self::DOCTORS),
            'clinic_name' => DemoRandom::pick(self::CLINICS),
            'issued_at' => $issued->toDateString(),
            'expires_at' => $expires->toDateString(),

            'right_sphere' => $rightSphere,
            'right_cylinder' => $hasAstigmatism ? DemoRandom::quarterStep(-2.25, -0.25) : null,
            'right_axis' => $hasAstigmatism ? DemoRandom::int(0, 180) : null,
            'right_add' => $needsAdd ? DemoRandom::quarterStep(0.75, 3.00) : null,

            'left_sphere' => $leftSphere,
            'left_cylinder' => $hasAstigmatism ? DemoRandom::quarterStep(-2.25, -0.25) : null,
            'left_axis' => $hasAstigmatism ? DemoRandom::int(0, 180) : null,
            'left_add' => $needsAdd ? DemoRandom::quarterStep(0.75, 3.00) : null,

            'pd' => DemoRandom::float(56.0, 70.0, 1),

            'is_verified' => $verified,
            'notes' => DemoRandom::chance(35) ? DemoRandom::pick(self::NOTES) : null,
        ]);

        $uploaded = $issued->addDays(DemoRandom::int(0, 21))->setTime(DemoRandom::int(9, 20), DemoRandom::int(0, 59));

        $prescription->forceFill([
            'verified_at' => $verified ? $uploaded->addHours(DemoRandom::int(2, 72)) : null,
            'verified_by' => $verified ? $this->verifierId() : null,
            'created_at' => $uploaded,
            'updated_at' => $uploaded,
        ])->save();
    }

    /**
     * Whoever signed the record off. Cached across calls because this runs
     * once per prescription and the staff list does not change mid-seed.
     */
    private function verifierId(): ?int
    {
        static $verifiers = null;

        $verifiers ??= User::whereIn('role', ['owner', 'staff'])
            ->where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)
            ->pluck('id')
            ->all();

        return $verifiers === [] ? null : DemoRandom::pick($verifiers);
    }
}
