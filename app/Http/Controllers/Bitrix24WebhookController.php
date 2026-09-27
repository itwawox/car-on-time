<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Исходящий вебхук Битрикс24: менеджер двигает сделку по стадиям в CRM — статус брони на сайте обновляется.
 */
class Bitrix24WebhookController extends Controller
{
    private const EVENTS = ['ONCRMDEALUPDATE' => 'deal', 'ONCRMLEADUPDATE' => 'lead'];

    public function __invoke(Request $request): JsonResponse
    {
        $token = Bitrix24Settings::inboundToken();
        if (! Bitrix24Settings::inboundEnabled() || ! $token || ! hash_equals($token, (string) $request->input('auth.application_token'))) {
            abort(403);
        }

        $entity = self::EVENTS[strtoupper((string) $request->input('event'))] ?? null;
        $id = (int) $request->input('data.FIELDS.ID');
        if (! $entity || $id <= 0) {
            return response()->json(['ok' => true, 'ignored' => 'event']);
        }

        $booking = Booking::query()->where('crm_entity', $entity)->where('crm_id', (string) $id)->first();
        if (! $booking) {
            return response()->json(['ok' => true, 'ignored' => 'unknown']);
        }

        $log = new IntegrationLog(['integration' => 'bitrix24', 'event' => 'webhook.'.$entity.'.update', 'request' => $request->except('auth')]);
        $log->subject()->associate($booking);

        try {
            $record = Bitrix24Client::fromSettings()?->call('crm.'.$entity.'.get', ['id' => $id]);
        } catch (Bitrix24Exception $e) {
            $log->fill(['status' => 'failed', 'error' => $e->getMessage()])->save();

            return response()->json(['ok' => false], 502);
        }

        $stage = (string) ($record[$entity === 'lead' ? 'STATUS_ID' : 'STAGE_ID'] ?? '');
        $status = Bitrix24Settings::statusForStage($stage);

        if ($status && $status !== $booking->status) {
            $booking->update(['status' => $status]);
            $log->fill(['status' => 'success', 'response' => ['stage' => $stage, 'status' => $status]])->save();
        } else {
            $log->fill(['status' => 'skipped', 'response' => ['stage' => $stage]])->save();
        }

        return response()->json(['ok' => true]);
    }
}
