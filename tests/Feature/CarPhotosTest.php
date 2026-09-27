<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Car;
use App\Support\CarPhotoMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

class CarPhotosTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $legacyDir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->dir = storage_path('framework/testing/image-cars-'.uniqid());
        $this->legacyDir = 'legacy-image/test-'.uniqid();
        File::ensureDirectoryExists($this->dir.'/old_image');
        File::ensureDirectoryExists($this->dir.'/new_image');
        File::ensureDirectoryExists(public_path($this->legacyDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::deleteDirectory(public_path($this->legacyDir));
        parent::tearDown();
    }

    /** Картинка-«машина»: прямоугольник в своей позиции — у разных машин разный хеш. */
    private function picture(string $path, int $width, int $height, int $variant): void
    {
        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel('white'));
        $block = new Imagick;
        $block->newImage(intdiv($width, 4), intdiv($height, 3), new ImagickPixel('black'));
        $image->compositeImage($block, Imagick::COMPOSITE_OVER, ($variant % 4) * intdiv($width, 4), intdiv($variant, 4) * intdiv($height, 3));
        $image->setImageFormat('jpeg');
        $image->writeImage($path);
    }

    private function car(string $name, int $variant): Car
    {
        $brand = Brand::query()->firstOrCreate(['slug' => 'kia'], ['name' => 'Kia']);
        $legacy = $this->legacyDir.'/'.$variant.'.jpg';
        $this->picture(public_path($legacy), 300, 167, $variant);

        return Car::query()->create([
            'brand_id' => $brand->id, 'name' => $name, 'slug' => str($name)->slug(), 'gearbox' => 'at',
            'seats' => 5, 'status' => 'published', 'legacy_image' => $legacy,
        ]);
    }

    private function pair(int $n, int $variant, string $oldName): void
    {
        $this->picture($this->dir."/old_image/{$n}_{$oldName}.jpg", 300, 167, $variant);
        $this->picture($this->dir."/new_image/{$n}-abc.jpg", 1888, 1040, $variant);
    }

    public function test_photos_are_matched_by_picture_not_by_file_name(): void
    {
        $rio = $this->car('Kia Rio', 1);
        $ceed = $this->car('Kia Ceed', 5);
        // Имена специально ничего не говорят о машине
        $this->pair(1, 5, 'arenda-prokat-avto-4');
        $this->pair(2, 1, 'arenda-prokat-avto-7');
        $this->pair(3, 10, 'mersedes-e250'); // машины с таким фото в каталоге нет

        $result = CarPhotoMatcher::match($this->dir, Car::all());

        $byNumber = collect($result['matched'])->mapWithKeys(fn ($m) => [$m['n'] => $m['car']->id]);
        $this->assertSame($ceed->id, $byNumber[1]);
        $this->assertSame($rio->id, $byNumber[2]);
        $this->assertSame([3], array_column($result['orphans'], 'n'));
    }

    public function test_import_uploads_renamed_photos_and_is_idempotent(): void
    {
        $rio = $this->car('Kia Rio', 1);
        $this->pair(1, 1, 'arenda-prokat-avto-kia-rio');
        $this->pair(2, 10, 'mersedes-e250');

        $this->artisan('cars:import-photos', ['dir' => $this->dir])->assertSuccessful();

        $media = $rio->fresh()->getFirstMedia('gallery');
        $this->assertNotNull($media);
        $this->assertSame('kia-rio.jpg', $media->file_name);
        $this->assertSame('Аренда Kia Rio в Крыму', $rio->fresh()->coverAlt());
        $this->assertTrue($media->hasGeneratedConversion('card'));
        $this->assertTrue($media->hasGeneratedConversion('large'));
        $this->assertLessThanOrEqual(1600, getimagesize($media->getPath())[0]);
        $this->assertFileExists($this->dir.'/renamed/kia-rio.jpg');
        $this->assertFileExists($this->dir.'/renamed/без-машины/mersedes-e250.jpg');
        $this->assertStringContainsString('conversions/kia-rio-card.webp', (string) $rio->fresh()->coverUrl('card'));
        $this->assertStringEndsWith('kia-rio.jpg', (string) $rio->fresh()->coverUrl('', false));

        // Повторный запуск ничего не дублирует
        $this->artisan('cars:import-photos', ['dir' => $this->dir])->assertSuccessful();
        $this->assertCount(1, $rio->fresh()->getMedia('gallery'));
        $this->assertSame($media->id, $rio->fresh()->getFirstMedia('gallery')->id);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $rio = $this->car('Kia Rio', 1);
        $this->pair(1, 1, 'arenda-prokat-avto-kia-rio');

        $this->artisan('cars:import-photos', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful();

        $this->assertCount(0, $rio->fresh()->getMedia('gallery'));
        $this->assertDirectoryDoesNotExist($this->dir.'/renamed');
    }

    public function test_photo_urls_work_on_https_pages(): void
    {
        // Регрессия: абсолютные http://…/storage/… на https-странице браузер блокирует как смешанный контент
        $this->assertSame('/storage', config('filesystems.disks.public.url'));

        $rio = $this->car('Kia Rio', 1);
        $this->pair(1, 1, 'arenda-prokat-avto-kia-rio');
        $this->artisan('cars:import-photos', ['dir' => $this->dir])->assertSuccessful();

        $html = $this->get('https://localhost/avto/'.$rio->slug)->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/(src|srcset)="http:\/\//', $html);
        $this->assertStringContainsString('og:image" content="https://', $html);
    }
}
