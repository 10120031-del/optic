<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The people: a shop owner, the staff who work the console, the riders who
 * carry the boxes, and the customers who buy.
 *
 * Every account lives on DemoConfig::EMAIL_DOMAIN and shares one password, so
 * a presenter can sign in as any of them mid-demo to show the same order from
 * the customer's side and the owner's side. That is also the marker DemoSeeder
 * uses to find and remove its own data, which is why nothing here may be
 * created on a different domain.
 *
 * The password is hashed once and handed to every row rather than being
 * hashed per user: bcrypt is deliberately slow, and fifty-odd calls to it is
 * most of the runtime of the whole seeder. Laravel's `hashed` cast passes an
 * already-hashed value through untouched, so this stays a normal assignment.
 */
class DemoUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<int, string> */
    private const MENS_NAMES = [
        'Karim', 'Rami', 'Ziad', 'Elie', 'Georges', 'Hadi', 'Nabil', 'Fadi', 'Marwan', 'Tarek',
        'Samir', 'Jad', 'Bassam', 'Wissam', 'Charbel', 'Omar', 'Youssef', 'Hussein', 'Ali', 'Ibrahim',
        'Antoine', 'Rabih', 'Michel', 'Khaled', 'Nadim',
    ];

    /** @var array<int, string> */
    private const WOMENS_NAMES = [
        'Rana', 'Lara', 'Maya', 'Nour', 'Yasmine', 'Dalia', 'Rima', 'Joelle', 'Carla', 'Nadine',
        'Hala', 'Sara', 'Farah', 'Reem', 'Layla', 'Zeina', 'Mira', 'Tala', 'Aya', 'Christelle',
        'Nayla', 'Sandra', 'Perla', 'Ghina', 'Rita',
    ];

    /** @var array<int, string> */
    private const FAMILY_NAMES = [
        'Haddad', 'Khoury', 'Aoun', 'Nassar', 'Saliba', 'Chami', 'Mansour', 'Fares', 'Younes', 'Rizk',
        'Sfeir', 'Daher', 'Karam', 'Hamdan', 'Ghanem', 'Sleiman', 'Tannous', 'Zeidan', 'Awad', 'Chidiac',
        'Maalouf', 'Sarkis', 'Antoun', 'Hobeika', 'Jaber', 'Kassem', 'Merhi', 'Traboulsi', 'Bassil', 'Azar',
    ];

    /** @var array<int, array{0: string, 1: string}> city => postal prefix */
    private const CITIES = [
        ['Beirut', '1107'],
        ['Jounieh', '1200'],
        ['Tripoli', '1300'],
        ['Saida', '1600'],
        ['Tyre', '1700'],
        ['Zahle', '1801'],
        ['Byblos', '1401'],
        ['Batroun', '1420'],
        ['Aley', '1502'],
        ['Broummana', '1509'],
        ['Antelias', '1201'],
        ['Nabatieh', '1700'],
        ['Baabda', '1503'],
        ['Zgharta', '1310'],
        ['Baalbek', '1810'],
    ];

    /** @var array<int, string> */
    private const STREETS = [
        'Hamra Street', 'Rue Verdun', 'Bliss Street', 'Gouraud Street', 'Sassine Square',
        'Mar Mikhael Street', 'Foch Street', 'Independence Avenue', 'Damascus Road', 'Corniche el Mazraa',
        'Rue Sursock', 'Achrafieh Main Road', 'Jal el Dib Highway', 'Old Souk Road', 'Cedars Avenue',
    ];

    /** @var array<int, string> */
    private const BIOS = [
        'Long-sighted since school, on my second pair from here.',
        'Screens all day — anti-blue-light is non-negotiable.',
        'I wear contacts on weekdays and frames at the weekend.',
        'Buying for the whole family, so I am here more often than most.',
        'Switched to progressives last year and never looked back.',
        'I run in the morning, so a sports pair lives in my bag.',
        'Prefer titanium — anything heavier and I feel it by lunchtime.',
        'Astigmatism in both eyes, so I am fussy about the axis.',
    ];

    public function run(): void
    {
        $password = Hash::make(DemoConfig::PASSWORD);

        $this->seedOwner($password);
        $this->seedStaff($password);
        $this->seedDelivery($password);
        $this->seedCustomers($password);
    }

    private function seedOwner(string $password): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'owner@'.DemoConfig::EMAIL_DOMAIN],
            [
                'first_name' => 'Hanan',
                'last_name' => 'Khoury',
                'password' => $password,
                'phone_number' => '+961 3 100 200',
                'role' => 'owner',
                'newsletter_opt_in' => false,
            ]
        );

        $owner->forceFill(['email_verified_at' => now()->subMonths(14)])->save();

        $owner->profile()->updateOrCreate([], [
            'description' => 'Owner. Opened the shop in 2011 and still grinds the tricky prescriptions herself.',
            'address_line' => '12 Sassine Square',
            'city' => 'Beirut',
            'postal_code' => '1107 2020',
            'country' => 'Lebanon',
        ]);
    }

    private function seedStaff(string $password): void
    {
        $staff = [
            ['first_name' => 'Marc', 'last_name' => 'Saliba', 'email' => 'marc.staff', 'phone_number' => '+961 3 100 201', 'description' => 'Front of house and prescription verification.'],
            ['first_name' => 'Yara', 'last_name' => 'Fares', 'email' => 'yara.staff', 'phone_number' => '+961 3 100 202', 'description' => 'Handles returns, exchanges and review moderation.'],
        ];

        foreach (array_slice($staff, 0, DemoConfig::STAFF) as $member) {
            $user = User::updateOrCreate(
                ['email' => $member['email'].'@'.DemoConfig::EMAIL_DOMAIN],
                [
                    'first_name' => $member['first_name'],
                    'last_name' => $member['last_name'],
                    'password' => $password,
                    'phone_number' => $member['phone_number'],
                    'role' => 'staff',
                    'newsletter_opt_in' => false,
                ]
            );

            $user->forceFill(['email_verified_at' => now()->subMonths(DemoRandom::int(4, 20))])->save();

            $user->profile()->updateOrCreate([], [
                'description' => $member['description'],
                'address_line' => DemoRandom::pick(self::STREETS),
                'city' => 'Beirut',
                'postal_code' => '1107 '.DemoRandom::int(2000, 2099),
                'country' => 'Lebanon',
            ]);
        }
    }

    private function seedDelivery(string $password): void
    {
        $riders = [
            ['first_name' => 'Elias', 'last_name' => 'Rizk', 'email' => 'elias.delivery', 'zone' => 'Beirut and the southern suburbs'],
            ['first_name' => 'Hadi', 'last_name' => 'Zeidan', 'email' => 'hadi.delivery', 'zone' => 'Mount Lebanon and the coast north of Jounieh'],
            ['first_name' => 'Nour', 'last_name' => 'Ghanem', 'email' => 'nour.delivery', 'zone' => 'Bekaa and the south'],
        ];

        foreach (array_slice($riders, 0, DemoConfig::DELIVERY) as $i => $rider) {
            $user = User::updateOrCreate(
                ['email' => $rider['email'].'@'.DemoConfig::EMAIL_DOMAIN],
                [
                    'first_name' => $rider['first_name'],
                    'last_name' => $rider['last_name'],
                    'password' => $password,
                    'phone_number' => '+961 3 100 3'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'role' => 'delivery',
                    'newsletter_opt_in' => false,
                ]
            );

            $user->forceFill(['email_verified_at' => now()->subMonths(DemoRandom::int(2, 12))])->save();

            $user->profile()->updateOrCreate([], [
                'description' => 'Delivery — covers '.$rider['zone'].'.',
                'address_line' => DemoRandom::pick(self::STREETS),
                'city' => DemoRandom::pick(self::CITIES)[0],
                'postal_code' => DemoRandom::int(1100, 1899).' '.DemoRandom::int(1000, 9999),
                'country' => 'Lebanon',
            ]);
        }
    }

    /**
     * Customers, each with a shipping address on their profile.
     *
     * Names are drawn without replacement from the pools above and the email
     * carries a sequence number, so two people called Rana Haddad can both
     * exist without colliding on the unique email index.
     *
     * A slice of them are left unverified and a slice opt out of the
     * newsletter — those two flags are what the verification banner and the
     * campaign audience counts key off, and a table where every row is
     * identical demonstrates neither.
     */
    private function seedCustomers(string $password): void
    {
        for ($i = 1; $i <= DemoConfig::CUSTOMERS; $i++) {
            $first = DemoRandom::chance(50)
                ? DemoRandom::pick(self::MENS_NAMES)
                : DemoRandom::pick(self::WOMENS_NAMES);

            $last = DemoRandom::pick(self::FAMILY_NAMES);

            [$city, $postalPrefix] = DemoRandom::pick(self::CITIES);

            $email = sprintf(
                '%s.%s%d@%s',
                strtolower($first),
                strtolower(str_replace(' ', '', $last)),
                $i,
                DemoConfig::EMAIL_DOMAIN
            );

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'password' => $password,
                    'phone_number' => sprintf('+961 %d %d %d', DemoRandom::int(3, 8), DemoRandom::int(100, 999), DemoRandom::int(100, 999)),
                    'role' => 'customer',
                    'newsletter_opt_in' => DemoRandom::chance(72),
                ]
            );

            $joined = DemoRandom::recentMoment(DemoConfig::HISTORY_DAYS + 210);

            $user->forceFill([
                // A tenth of the list has registered but never clicked the
                // link — that is the state the verification notice exists for.
                'email_verified_at' => DemoRandom::chance(90) ? $joined->addMinutes(DemoRandom::int(2, 240)) : null,
                'created_at' => $joined,
                'updated_at' => $joined,
            ])->save();

            $user->profile()->updateOrCreate([], [
                'description' => DemoRandom::chance(45) ? DemoRandom::pick(self::BIOS) : null,
                'address_line' => DemoRandom::int(1, 180).' '.DemoRandom::pick(self::STREETS),
                'city' => $city,
                'postal_code' => $postalPrefix.' '.DemoRandom::int(1000, 9999),
                'country' => 'Lebanon',
            ]);
        }
    }
}
