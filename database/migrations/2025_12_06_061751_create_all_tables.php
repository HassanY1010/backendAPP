<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 2. categories
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('image')->nullable();
            $table->string('color', 20)->default('#3b82f6');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // 3. category_fields
        Schema::create('category_fields', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->string('name');
            $table->string('label');
            $table->enum('type', ['text', 'number', 'select', 'checkbox', 'textarea', 'date'])->default('text');
            $table->text('options')->nullable(); // JSON array
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });

        // 4. ads
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('category_id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->decimal('price', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('location');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('status', ['pending', 'active', 'rejected', 'sold', 'expired', 'inactive'])->default('pending');
            $table->integer('views')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_whatsapp', 20)->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('featured_until')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->fullText(['title', 'description', 'location']);
        });

        // 5. ad_custom_fields
        Schema::create('ad_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->unsignedInteger('field_id'); // Referencing category_fields.id (INT UNSIGNED)
            $table->text('value')->nullable();
            $table->timestamps();

            $table->foreign('field_id')->references('id')->on('category_fields')->onDelete('cascade');
            $table->unique(['ad_id', 'field_id']);
        });

        // 6. ad_images
        Schema::create('ad_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->string('image_path');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_main')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('alt_text')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 7. favorites
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'ad_id']);
        });

        // 8. conversations
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('sender_deleted_at')->nullable();
            $table->timestamp('receiver_deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['ad_id', 'sender_id', 'receiver_id']);
        });

        // 9. messages
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->enum('message_type', ['text', 'image', 'file', 'location'])->default('text');
            $table->string('file_url', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('deleted_by_sender')->default(false);
            $table->boolean('deleted_by_receiver')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // 10. reports
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ad_id')->constrained('ads')->onDelete('cascade');
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['ad', 'user', 'message'])->default('ad');
            $table->enum('reason', ['spam', 'inappropriate', 'fraud', 'wrong_category', 'duplicate', 'offensive', 'other']);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'investigating', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 11. reviews
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ad_id')->nullable()->constrained('ads')->onDelete('set null');
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->unique(['reviewer_id', 'reviewed_id', 'ad_id']);
        });

        // 12. notifications
        Schema::create('notifications_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 50);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // 13. payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('ad_id')->nullable()->constrained('ads')->onDelete('set null');
            $table->enum('type', ['featured', 'urgent', 'premium', 'subscription']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('payment_method', 50);
            $table->string('transaction_id')->unique()->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 14. plans
        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('duration_days');
            $table->integer('max_ads')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 15. subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedInteger('plan_id');
            $table->string('plan_name', 100);
            $table->decimal('price', 10, 2);
            $table->integer('duration_days');
            $table->integer('max_ads')->nullable();
            $table->json('features')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();

            // Although plans.id is INT, we can typically rely on the integer type in SQLite/MySQL. 
            // In strict mode, we should match types. plans is increments() -> unsigned integer.
        });

        // 16. locations
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->enum('type', ['country', 'city', 'area'])->default('city');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('locations')->onDelete('cascade');
        });

        // 17. statistics
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('total_ads')->default(0);
            $table->integer('active_ads')->default(0);
            $table->integer('pending_ads')->default(0);
            $table->integer('new_users')->default(0);
            $table->integer('total_users')->default(0);
            $table->integer('page_views')->default(0);
            $table->integer('ad_views')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->decimal('revenue', 15, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('statistics');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('payments');
        // Renamed to avoid collision with Laravel's built-in notifications if any
        Schema::dropIfExists('notifications_table');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('ad_images');
        Schema::dropIfExists('ad_custom_fields');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('category_fields');
        Schema::dropIfExists('categories');
        Schema::enableForeignKeyConstraints();
    }
};
