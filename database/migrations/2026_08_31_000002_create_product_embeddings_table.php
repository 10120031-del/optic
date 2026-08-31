<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Neural embeddings of the catalog, one row per frame or contact lens.
     *
     * The vectors are produced offline by `php artisan catalog:embed`, which
     * runs the all-MiniLM-L6-v2 sentence transformer over a written
     * description of each product (see App\Services\CatalogEmbedder). PHP
     * cannot run an ONNX model, and nothing about a product's text changes
     * per request, so inference happens at write time and the storefront
     * only ever does arithmetic on what is stored here.
     *
     * That split is what keeps the runtime server exactly as it was: no
     * Node, no Python, no model files, no outbound calls. Same shape as the
     * MediaPipe face landmarker, which is also downloaded at build time.
     */
    public function up(): void
    {
        Schema::create('product_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('embeddable_type');
            $table->unsignedBigInteger('embeddable_id');

            // Which model wrote this vector. Vectors from different models
            // are not comparable, so a model swap has to invalidate the
            // whole table rather than silently mixing coordinate spaces.
            $table->string('model');
            $table->unsignedSmallInteger('dimensions');

            // Base64 of packed little-endian float32s, not JSON: a third of
            // the size, and it decodes with a single unpack() instead of a
            // 384-element json_decode on every product on every request.
            // Base64 rather than a raw BLOB so the value stays a plain
            // string through every PDO driver, with no null-byte handling.
            $table->text('vector');

            // SHA-256 of the exact text that was embedded, so a re-run only
            // pays for products whose description actually changed.
            $table->string('content_hash', 64);

            $table->timestamps();

            $table->unique(['embeddable_type', 'embeddable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_embeddings');
    }
};
