<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Brand;
use App\Models\Car;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $brand = Brand::query()->create(['slug' => 'kia', 'name' => 'Kia']);
        $car = Car::query()->create(['brand_id' => $brand->id, 'name' => 'Kia Rio', 'slug' => 'kia-rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published', 'min_days' => 1]);
        $this->booking = Booking::query()->create([
            'car_id' => $car->id, 'phone' => '+79780000000', 'status' => 'confirmed', 'total' => 6000, 'source' => 'card',
            'starts_at' => Carbon::parse('+2 days 10:00'), 'ends_at' => Carbon::parse('+5 days 10:00'),
        ]);
    }

    private function upload(array $files, bool $consent = true)
    {
        return $this->post(route('booking.documents', $this->booking->public_token), array_filter([
            'docs' => $files,
            'documents_consent' => $consent ? '1' : null,
        ]));
    }

    public function test_client_uploads_documents_privately_and_manager_is_told(): void
    {
        $this->get($this->booking->statusUrl())->assertOk()->assertSee('Документы заранее');

        $this->upload(['passport' => UploadedFile::fake()->image('p.jpg'), 'license_front' => UploadedFile::fake()->image('l.jpg')])
            ->assertRedirect($this->booking->statusUrl().'#documents');

        $media = $this->booking->fresh()->getMedia('documents');
        $this->assertCount(2, $media);
        $this->assertSame('local', $media->first()->disk);
        $this->assertNotNull($this->booking->fresh()->documents_consent_at);
        $this->assertStringContainsString('загрузил документы', $this->booking->events()->where('type', 'note')->sole()->comment);
        $this->get($this->booking->statusUrl())->assertSee('✓ получено');
    }

    public function test_consent_and_file_type_are_required(): void
    {
        $this->upload(['passport' => UploadedFile::fake()->image('p.jpg')], consent: false)->assertSessionHasErrors('documents_consent');
        $this->upload(['passport' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('docs.passport');
        $this->assertCount(0, $this->booking->fresh()->getMedia('documents'));
    }

    public function test_new_file_of_same_type_replaces_old_one(): void
    {
        $this->upload(['passport' => UploadedFile::fake()->image('old.jpg')]);
        $this->upload(['passport' => UploadedFile::fake()->image('new.jpg')]);

        $this->assertCount(1, $this->booking->fresh()->getMedia('documents'));
    }

    public function test_documents_are_not_accepted_after_pickup(): void
    {
        $this->booking->update(['starts_at' => now()->subHour()]);

        $this->upload(['passport' => UploadedFile::fake()->image('p.jpg')])->assertStatus(422);
    }

    public function test_act_photos_are_visible_by_booking_link_only(): void
    {
        $photo = $this->booking->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('act_pickup');
        $doc = $this->booking->addMedia(UploadedFile::fake()->image('passport.jpg'))->withCustomProperties(['type' => 'passport'])->toMediaCollection('documents');
        $this->booking->update(['pickup_mileage' => 42000, 'pickup_fuel' => 100]);

        $this->get($this->booking->statusUrl())->assertSee('Акт осмотра')->assertSee('пробег 42 000 км');
        $this->get(route('booking.act-photo', [$this->booking->public_token, $photo]))->assertOk();
        // Документ клиента по этой ссылке не отдаётся, чужая заявка — тоже
        $this->get(route('booking.act-photo', [$this->booking->public_token, $doc]))->assertNotFound();
        $this->get(route('booking.act-photo', ['aaaaaaaaaaaa', $photo]))->assertNotFound();
    }

    public function test_documents_are_deleted_after_retention_period(): void
    {
        Setting::put('documents_retention_days', 30);
        $this->upload(['passport' => UploadedFile::fake()->image('p.jpg')]);
        $this->booking->update(['starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(35)]);

        $this->artisan('documents:prune')->assertSuccessful();

        $this->assertCount(0, $this->booking->fresh()->getMedia('documents'));
        $this->assertNotNull($this->booking->fresh()->documents_deleted_at);
    }

    public function test_manager_sees_document_and_act_sections(): void
    {
        $this->actingAs(User::factory()->role('manager')->create());

        $this->get('/admin/bookings/'.$this->booking->id.'/edit')->assertOk()->assertSee('Документы клиента')->assertSee('Акт осмотра');
    }
}
