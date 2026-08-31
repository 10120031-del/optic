<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named drop the owner curates by hand — "Autumn 25", "Titanium
     * Series" — holding any mix of frames and contact lenses.
     *
     * announced_at is the whole point of the table: null means the owner is
     * still assembling it in the staff console and nobody has been told,
     * a timestamp means the announcement went out. It is set once, by
     * Admin\CollectionController::announce, and never cleared — so a
     * collection can be edited afterwards without re-blasting every
     * customer, and the console can show when it dropped.
     */
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('announced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->nullable()
                ->comment('Customers notified when it was announced; null until then');
            $table->timestamps();

            // The storefront only ever lists announced, active collections,
            // newest drop first.
            $table->index(['is_active', 'announced_at']);
        });

        // Polymorphic so one collection can mix frames and contact lenses.
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->morphs('item');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'item_type', 'item_id'], 'collection_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
