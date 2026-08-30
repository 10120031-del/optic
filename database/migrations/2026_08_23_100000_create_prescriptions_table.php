<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A customer's prescription on file: the source-of-record document
     * (photo/PDF upload) plus the values used to grind lenses, an
     * expiration (prescriptions typically expire 1-2 years after issue),
     * and a verification flag for staff sign-off. Cart/order eyeglass
     * lines keep their own snapshot of the numbers actually used (the
     * customer can adjust slightly at checkout), and optionally reference
     * back to this record via prescription_id for the paper trail.
     */
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable()->comment('Uploaded photo/PDF of the physical prescription');
            $table->string('doctor_name')->nullable();
            $table->string('clinic_name')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            // Left eye (OS)
            $table->decimal('left_sphere', 5, 2)->nullable();
            $table->decimal('left_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('left_axis')->nullable();
            $table->decimal('left_add', 4, 2)->nullable();

            // Right eye (OD)
            $table->decimal('right_sphere', 5, 2)->nullable();
            $table->decimal('right_cylinder', 5, 2)->nullable();
            $table->unsignedSmallInteger('right_axis')->nullable();
            $table->decimal('right_add', 4, 2)->nullable();

            $table->decimal('pd', 4, 1)->nullable()->comment('Pupillary distance in mm');

            $table->boolean('is_verified')->default(false)->comment('Staff has confirmed the upload matches the entered values');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
