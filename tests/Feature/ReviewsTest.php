<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function car(): Car
    {
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);

        return Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published']);
    }

    private function payload(array $extra = []): array
    {
        return ['author' => 'Анна', 'rating' => 5, 'body' => 'Машину привезли вовремя, всё чисто и понятно.', 'pd_consent' => '1', ...$extra];
    }

    public function test_submitted_review_waits_for_moderation(): void
    {
        $car = $this->car();

        $this->post('/otzyvy', $this->payload(['car_id' => $car->id, 'phone' => '+79780000000']))
            ->assertRedirect()->assertSessionHas('review_sent');

        $review = Review::query()->sole();
        $this->assertFalse($review->is_published);
        $this->assertSame($car->id, $review->car_id);
        $this->get('/otzyvy')->assertOk()->assertDontSee('Машину привезли вовремя')->assertDontSee('AggregateRating');
    }

    public function test_published_review_is_shown_with_real_rating_and_phone_is_hidden(): void
    {
        $car = $this->car();
        Review::query()->create(['author' => 'Иван', 'phone' => '+79781112233', 'rating' => 4, 'body' => 'Хорошая машина, спасибо за быструю выдачу.', 'car_id' => $car->id, 'is_published' => true]);
        Review::query()->create(['author' => 'Олег', 'rating' => 5, 'body' => 'Отлично.', 'is_published' => true]);

        $this->get('/otzyvy')->assertOk()
            ->assertSee('Хорошая машина')
            ->assertSee('"ratingValue":4.5', false)
            ->assertSee('"reviewCount":2', false)
            ->assertDontSee('+79781112233');
        $this->get('/avto/kia-rio')->assertSee('Хорошая машина')->assertDontSee('Отлично.');
        $this->get('/')->assertSee('Отзывы клиентов');
        $this->assertNotNull(Review::query()->where('author', 'Иван')->value('reviewed_at'));
    }

    public function test_validation_and_honeypot(): void
    {
        $this->post('/otzyvy', $this->payload(['body' => 'Коротко']))->assertSessionHasErrors('body');
        $this->post('/otzyvy', $this->payload(['pd_consent' => null]))->assertSessionHasErrors('pd_consent');
        $this->post('/otzyvy', $this->payload(['rating' => 7]))->assertSessionHasErrors('rating');

        $this->post('/otzyvy', $this->payload(['website' => 'http://spam']))->assertSessionHas('review_sent');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_home_has_no_reviews_block_without_reviews(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="h-reviews"', false);
        $this->get('/sitemap.xml')->assertSee('/otzyvy');
    }
}
