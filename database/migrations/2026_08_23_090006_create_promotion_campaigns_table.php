<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A record of promotional email blasts the shop owner sends (audience,
     * content, when it went out). Actual delivery happens through the mail
     * queue against users where newsletter_opt_in = true; this table is the
     * history/audit trail the admin dashboard lists.
     */
    public function up(): void
    {
        Schema::create('promotion_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject');
            $table->text('body');
            $table->enum('audience', ['all', 'customers', 'newsletter_subscribers'])->default('newsletter_subscribers');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_campaigns');
    }
};
