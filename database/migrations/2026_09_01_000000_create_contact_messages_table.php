<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enquiries sent from the "Contact us" section of the About page.
     *
     * Stored rather than mailed: the shop already reads its notifications in
     * the staff console (see App\Services\Notifier), so a message that lives
     * in a table can be assigned a status and worked through like returns and
     * reviews are — and cannot be lost to a bouncing SMTP host.
     *
     * user_id is nullable and only filled when the sender happened to be
     * signed in: the form is open to visitors, which is the whole point of it.
     * The name/email typed into the form are kept verbatim either way, so the
     * record still reads correctly if the account is later deleted.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Set when the sender was signed in; guests leave this null');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('topic')->default('general')->comment('general, order, prescription, returns, wholesale, other');
            $table->text('message');
            $table->string('status')->default('new')->comment('new, read, closed');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            // The console lists newest-unhandled-first and badges the count of
            // new ones on every admin page render.
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
