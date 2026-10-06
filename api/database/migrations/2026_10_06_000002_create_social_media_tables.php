<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Social Accounts (Connected Pages/Channels/Profiles per user)
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook, instagram, youtube, linkedin
            $table->string('account_id'); // Page ID, Channel ID, Org ID, or Account ID
            $table->string('account_name');
            $table->string('account_username')->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token')->nullable();
            $table->string('status')->default('connected'); // connected, expired, disconnected
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });

        // 2. Social Posts (Draft, Scheduled, Published Posts per user)
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->json('media_urls')->nullable();
            $table->json('platforms'); // ['facebook', 'instagram', ...]
            $table->json('account_ids')->nullable(); // IDs of connected social_accounts
            $table->string('status')->default('draft'); // draft, scheduled, publishing, published, failed
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->json('platform_post_ids')->nullable(); // External IDs/URLs on platforms
            $table->text('error_message')->nullable();
            $table->json('metrics')->nullable(); // likes, comments, shares, views
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('scheduled_at');
        });

        // 3. Social Media Platform Settings & API Keys (Scoped strictly per user)
        Schema::create('social_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // facebook, instagram, youtube, linkedin
            $table->string('app_id')->nullable(); // or client_id
            $table->string('app_secret')->nullable(); // or client_secret
            $table->string('api_key')->nullable();
            $table->text('access_token')->nullable();
            $table->string('page_or_channel_id')->nullable();
            $table->string('webhook_verify_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_settings');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
