<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\TelegramNotifier;
use App\Support\Attribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «Перезвоните мне» (плавающая кнопка), заявка для юрлиц и «Сдать авто». Обязателен только телефон.
 */
class LeadController extends Controller
{
    public function store(Request $request, TelegramNotifier $telegram): RedirectResponse|JsonResponse
    {
        if ($request->filled('website')) { // ловушка для ботов
            return $this->done($request);
        }

        $data = $request->validate([
            'type' => ['required', 'in:callback,corporate,owner'],
            'phone' => ['required', 'string', 'min:10', 'max:32', function ($attr, $value, $fail) {
                if (strlen(preg_replace('/\D/', '', (string) $value)) < 10) {
                    $fail('Проверьте номер телефона — не хватает цифр.');
                }
            }],
            'name' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'inn' => ['nullable', 'string', 'max:20'],
            'message' => ['nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'array'],
            'details.owner_kind' => ['nullable', 'in:'.implode(',', array_keys(Lead::OWNER_KINDS))],
            'details.city' => ['nullable', 'string', 'max:80'],
            'details.car' => ['nullable', 'string', 'max:120'],
            'details.year' => ['nullable', 'integer', 'between:1990,'.(now()->year + 1)],
            'details.gearbox' => ['nullable', 'in:at,mt'],
            'details.cars_count' => ['nullable', 'integer', 'between:1,500'],
            'pd_consent' => ['accepted'],
        ], [
            'pd_consent.accepted' => 'Отметьте согласие на обработку персональных данных.',
            'phone.required' => 'Укажите телефон — перезвоним на него.',
            'phone.min' => 'Проверьте номер телефона — не хватает цифр.',
            'details.year.between' => 'Проверьте год выпуска.',
        ]);

        $details = $data['type'] === 'owner'
            ? array_filter(array_map(fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $data['details'] ?? []), fn ($v) => $v !== null && $v !== '')
            : [];

        $lead = Lead::query()->create([
            ...collect($data)->except(['pd_consent', 'details'])->all(),
            'details' => $details ?: null,
            'consent_at' => now(),
            'message' => isset($data['message']) ? trim(strip_tags($data['message'])) : null,
            'page' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
            'status' => 'new',
            'utm' => Attribution::fromRequest($request),
        ]);
        $telegram->leadCreated($lead);

        return $this->done($request);
    }

    private function done(Request $request): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('lead_sent', true)->withFragment('lead-form');
    }
}
