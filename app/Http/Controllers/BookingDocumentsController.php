<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Документы до выдачи (паспорт, права) и фото акта осмотра на странице заявки.
 * Доступ — только по ссылке заявки; документы клиенту обратно не показываем (только отметку «получено»).
 */
class BookingDocumentsController extends Controller
{
    public function upload(Request $request, string $token, TelegramNotifier $telegram): RedirectResponse
    {
        $booking = Booking::query()->where('public_token', $token)->firstOrFail();
        abort_unless(self::acceptsDocuments($booking), 422, 'Документы по этой заявке больше не принимаем.');

        $rules = ['documents_consent' => ['accepted']];
        foreach (array_keys(Booking::DOCUMENTS) as $type) {
            $rules['docs.'.$type] = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:10240'];
        }
        $data = $request->validate($rules, [
            'documents_consent.accepted' => 'Нужно согласие на обработку документов.',
            'docs.*.mimes' => 'Подойдёт фото (JPG, PNG, HEIC) или PDF.',
            'docs.*.max' => 'Файл больше 10 МБ — сфотографируйте ещё раз или сожмите.',
        ]);

        $files = array_filter($data['docs'] ?? []);
        if (! $files) {
            return back()->withErrors(['docs' => 'Выберите хотя бы один файл.']);
        }

        foreach ($files as $type => $file) {
            // Новый файл того же вида заменяет старый
            $booking->getMedia('documents')->filter(fn (Media $m) => $m->getCustomProperty('type') === $type)->each->delete();
            $booking->addMedia($file)
                ->usingFileName($type.'-'.bin2hex(random_bytes(8)).'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg'))
                ->withCustomProperties(['type' => $type])
                ->toMediaCollection('documents');
        }

        $booking->forceFill(['documents_consent_at' => now(), 'documents_uploaded_at' => now(), 'documents_deleted_at' => null])->saveQuietly();
        $names = implode(', ', array_map(fn ($t) => mb_strtolower(Booking::DOCUMENTS[$t]), array_keys($files)));
        $booking->events()->create(['type' => 'note', 'comment' => 'Клиент загрузил документы: '.$names]);
        $telegram->send('📄 Заявка №'.$booking->id.': клиент загрузил документы ('.count($files).' файл.)', ['booking' => $booking->id]);

        return redirect()->to($booking->statusUrl().'#documents')->with('documents_saved', true);
    }

    /** Фото акта осмотра для клиента — только свои и только из коллекций акта. */
    public function actPhoto(string $token, Media $media): BinaryFileResponse
    {
        $booking = Booking::query()->where('public_token', $token)->firstOrFail();
        abort_unless($media->model_type === Booking::class && (int) $media->model_id === $booking->id
            && in_array($media->collection_name, ['act_pickup', 'act_return'], true), 404);

        return response()->file($media->getPath(), ['Cache-Control' => 'private, max-age=3600', 'X-Robots-Tag' => 'noindex']);
    }

    /** Принимаем документы, пока машина ещё не выдана и заявка не отклонена. */
    public static function acceptsDocuments(Booking $booking): bool
    {
        return $booking->status !== 'declined' && ! $booking->starts_at?->isPast();
    }
}
