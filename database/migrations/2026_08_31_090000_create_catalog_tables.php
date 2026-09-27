<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('reliability')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('car_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('body_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_per_day')->default(0);
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('starts_month');
            $table->unsignedTinyInteger('starts_day');
            $table->unsignedTinyInteger('ends_month');
            $table->unsignedTinyInteger('ends_day');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->index();
            $table->string('short_name')->nullable();
            $table->string('type')->default('city');
            $table->string('hours_from')->nullable();
            $table->string('hours_to')->nullable();
            $table->unsignedInteger('price_1_day')->nullable();
            $table->unsignedInteger('price_2_days')->nullable();
            $table->unsignedInteger('price_3plus')->nullable();
            $table->unsignedInteger('night_price')->nullable();
            $table->boolean('is_default_pickup')->default(false);
            $table->boolean('seo_enabled')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->longText('intro')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('body_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('gearbox')->default('at');
            $table->string('fuel')->nullable();
            $table->string('engine')->nullable();
            $table->unsignedTinyInteger('seats')->default(5);
            $table->string('consumption')->nullable();
            $table->string('drivetrain')->nullable();
            $table->unsignedInteger('deposit')->default(0);
            $table->unsignedTinyInteger('min_age')->default(22);
            $table->unsignedTinyInteger('min_experience')->default(2);
            $table->unsignedTinyInteger('min_days')->default(2);
            $table->unsignedInteger('daily_km')->default(300);
            $table->longText('description')->nullable();
            $table->longText('seo_text')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('status')->default('published');
            $table->string('legacy_image')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('car_car_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('car_class_id')->constrained()->cascadeOnDelete();
            $table->unique(['car_id', 'car_class_id']);
        });

        Schema::create('car_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->unique(['car_id', 'feature_id']);
        });

        Schema::create('car_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('days_from');
            $table->unsignedSmallInteger('days_to')->nullable();
            $table->unsignedInteger('price');
            $table->timestamps();
        });

        Schema::create('car_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('phone');
            $table->boolean('telegram')->default(false);
            $table->boolean('max')->default(false);
            $table->boolean('whatsapp')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('return_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->json('extras')->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->string('status')->default('new');
            $table->string('source')->default('card');
            $table->text('notes')->nullable();
            $table->text('quiz_answers')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('h1')->nullable();
            $table->longText('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->longText('answer');
            $table->string('group')->default('site');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('body');
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status')->default(301);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('car_blocks');
        Schema::dropIfExists('car_prices');
        Schema::dropIfExists('car_feature');
        Schema::dropIfExists('car_car_class');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('seasons');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('features');
        Schema::dropIfExists('body_types');
        Schema::dropIfExists('car_classes');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('partners');
    }
};
