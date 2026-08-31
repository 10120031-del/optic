<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The in-app inbox behind both the storefront bell and the staff
     * console's Inbox tab. This is Laravel's standard database-notification
     * table (App\Notifications\InboxNotification writes to it), so
     * $user->notifications, ->unreadNotifications and ->markAsRead() all
     * work as shipped.
     *
     * notifiable is polymorphic in Laravel's design, but here it is always a
     * user — a customer for order/return/prescription/review news, the shop
     * owner for anything needing their attention. The payload in `data`
     * carries a stable `event` key ('order.status', 'stock.out', ...), the
     * title/body the inbox renders, a relative `url` to deep-link to, and a
     * `level` that picks the badge colour.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->comment('Notification class that produced the row');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The header bell counts unread rows for the signed-in user on
            // every page render — keep that a single index hit.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_inbox_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
