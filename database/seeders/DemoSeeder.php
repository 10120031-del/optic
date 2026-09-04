<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PromotionCampaign;
use App\Models\User;
use Database\Seeders\Demo\DemoCatalogSeeder;
use Database\Seeders\Demo\DemoConfig;
use Database\Seeders\Demo\DemoEngagementSeeder;
use Database\Seeders\Demo\DemoOrderSeeder;
use Database\Seeders\Demo\DemoPrescriptionSeeder;
use Database\Seeders\Demo\DemoRandom;
use Database\Seeders\Demo\DemoReturnSeeder;
use Database\Seeders\Demo\DemoReviewSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A whole shop's worth of plausible data, for showing the application to
 * people.
 *
 *     php artisan demo:seed
 *     php artisan db:seed --class=DemoSeeder --force
 *
 * Every screen in the application is meant to have something real on it after
 * this runs: a catalogue wide enough that the filters bite, customers with
 * order histories, prescriptions in each state the shop has to handle, five
 * months of orders walking the pipeline, returns waiting on a decision,
 * reviews waiting on moderation, browsing history behind the trends charts,
 * and inboxes with unread rows in them.
 *
 * Three properties are worth knowing about before running it anywhere:
 *
 * It is re-runnable. Everything it invents is attached to accounts on
 * DemoConfig::EMAIL_DOMAIN, and purge() removes exactly that before
 * rebuilding. Real accounts, real orders and staff-uploaded product photos
 * are never touched — which also means running it twice does not double the
 * data.
 *
 * It is deterministic. One fixed seed drives every draw, so the same command
 * produces the same shop every time: the same best seller, the same revenue
 * curve, the same order numbers. A demo rehearsed on Monday still matches on
 * Friday.
 *
 * It runs with model events muted. The observers in App\Observers would
 * otherwise fire a notification per row — a hundred and forty "new order"
 * alerts dated today, for orders months old — and the embedding observer
 * would try to vectorise the catalogue one product at a time. Both are
 * handled deliberately instead: notifications are written directly in
 * DemoEngagementSeeder, and embeddings are a single `php artisan catalog:embed`
 * afterwards.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DemoRandom::seed();

        $this->command?->warn('Removing any previous demo data...');
        $this->purge();

        // Reference data first: face shapes and lens features are foreign
        // keys for everything that follows, and the base catalogue is what
        // DemoCatalogSeeder widens.
        $this->call([
            FaceShapeSeeder::class,
            LensFeatureSeeder::class,
            CatalogSeeder::class,
        ]);

        // Order matters from here down. Orders need customers and
        // prescriptions; reviews and returns need delivered orders; the
        // engagement tables need all of it.
        $this->call([
            DemoCatalogSeeder::class,
            DemoUserSeeder::class,
            DemoPrescriptionSeeder::class,
            DemoOrderSeeder::class,
            DemoReturnSeeder::class,
            DemoReviewSeeder::class,
            DemoEngagementSeeder::class,
        ]);

        $this->summarise();
    }

    /**
     * Remove the previous run, and nothing else.
     *
     * The demo e-mail domain is the only thing that marks a row as ours, so
     * every delete here is anchored to it. Order matters: orders hold a
     * restricting foreign key to users, so they have to go before the people
     * who placed them — everything hanging off an order (lines, features,
     * payments, history, returns and their items) follows on the database's
     * own cascades, as do profiles, carts, prescriptions and reviews when the
     * user row finally goes.
     *
     * Catalogue rows are deliberately left alone. They are keyed on SKU and
     * rewritten by updateOrCreate, so there is nothing to clean up, and
     * deleting products would take staff-uploaded photos with them.
     */
    private function purge(): void
    {
        $userIds = User::where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)->pluck('id');

        if ($userIds->isNotEmpty()) {
            Order::whereIn('user_id', $userIds)->delete();

            DB::table('product_views')->whereIn('user_id', $userIds)->delete();

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->delete();

            User::whereIn('id', $userIds)->delete();
        }

        // Guests who wrote in, and the anonymous browsing sessions — neither
        // hangs off a user row, so neither is covered by the cascade above.
        ContactMessage::where('email', 'like', '%@'.DemoConfig::EMAIL_DOMAIN)->delete();
        DB::table('product_views')->where('session_id', 'like', 'demo-%')->delete();

        // Campaigns and collections are keyed by title/slug and rewritten in
        // place, so they need no purge — but a campaign created by a demo
        // owner who has just been deleted would be left orphaned with a null
        // author, which is harmless and matches the nullOnDelete the schema
        // asks for.
        PromotionCampaign::whereNull('created_by')->whereIn('title', [
            'Autumn 26 launch',
            'Free anti-blue-light week',
            'Prescription check reminder',
            'Titanium Series announcement',
            'Summer sunglasses',
        ])->delete();
    }

    /**
     * Print what was built and how to sign in, so whoever runs this on a
     * server does not have to go looking in the database for an account.
     */
    private function summarise(): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        $domain = DemoConfig::EMAIL_DOMAIN;

        $command->newLine();
        $command->info('Demo data ready.');
        $command->newLine();

        $command->table(['Table', 'Rows'], [
            ['Frames', DB::table('frames')->count()],
            ['Contact lenses', DB::table('contact_lenses')->count()],
            ['Collections', DB::table('collections')->count()],
            ['Users', DB::table('users')->count()],
            ['Prescriptions', DB::table('prescriptions')->count()],
            ['Orders', DB::table('orders')->count()],
            ['Order lines', DB::table('order_eyeglasses')->count() + DB::table('order_contact_lenses')->count()],
            ['Payments', DB::table('payments')->count()],
            ['Returns', DB::table('order_returns')->count()],
            ['Reviews', DB::table('reviews')->count()],
            ['Product views', DB::table('product_views')->count()],
            ['Contact messages', DB::table('contact_messages')->count()],
            ['Notifications', DB::table('notifications')->count()],
        ]);

        $command->newLine();
        $command->info('Sign in with any of these — the password is: '.DemoConfig::PASSWORD);
        $command->newLine();

        $command->table(['Role', 'Email'], [
            ['Owner', "owner@{$domain}"],
            ['Staff', "marc.staff@{$domain}"],
            ['Staff', "yara.staff@{$domain}"],
            ['Delivery', "elias.delivery@{$domain}"],
            ['Customer', User::where('role', 'customer')
                ->where('email', 'like', "%@{$domain}")
                ->withCount('orders')
                ->orderByDesc('orders_count')
                ->value('email') ?? '—'],
        ]);

        $command->newLine();
        $command->comment('Run `php artisan catalog:embed` next if you want the semantic search and recommender to cover the new products.');
    }
}
