<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Car;
use App\Models\CarBlock;
use App\Support\Availability;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Шахматка: машины × дни. Брони, ручные блокировки и заявки, которые ещё в работе. */
class FleetCalendar extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Календарь занятости';

    protected static string|\UnitEnum|null $navigationGroup = 'Заявки';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Календарь занятости';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.pages.fleet-calendar';

    public const DAYS = 21;

    public string $from = '';

    public string $search = '';

    public function mount(): void
    {
        $this->from = now()->startOfDay()->toDateString();
    }

    public function shift(int $days): void
    {
        $this->from = Carbon::parse($this->from)->addDays($days)->toDateString();
    }

    public function today(): void
    {
        $this->from = now()->startOfDay()->toDateString();
    }

    /** @return list<Carbon> */
    public function days(): array
    {
        $start = Carbon::parse($this->from);

        return array_map(fn ($i) => $start->copy()->addDays($i), range(0, self::DAYS - 1));
    }

    /**
     * @return Collection<int, array{car: Car, cells: list<array{kind: ?string, label: ?string, url: ?string}>, busy: int}>
     */
    public function rows(): Collection
    {
        $days = $this->days();
        $start = $days[0]->copy();
        $end = end($days)->copy()->endOfDay();

        $cars = Car::query()->published()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')->get(['id', 'name']);

        $blocks = CarBlock::query()->whereIn('car_id', $cars->modelKeys())->overlapping($start, $end)->get()->groupBy('car_id');
        $pending = Booking::query()->whereIn('car_id', $cars->modelKeys())
            ->whereIn('status', ['new', ...Booking::IN_PROGRESS])
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->get(['id', 'car_id', 'starts_at', 'ends_at', 'status'])->groupBy('car_id');

        return $cars->map(function (Car $car) use ($days, $blocks, $pending) {
            $cells = [];
            $busy = 0;
            foreach ($days as $day) {
                $dayEnd = $day->copy()->endOfDay();
                $block = ($blocks[$car->id] ?? collect())->first(fn (CarBlock $b) => $b->starts_at < $dayEnd && $b->ends_at > $day);
                $request = ($pending[$car->id] ?? collect())->first(fn (Booking $b) => $b->starts_at < $dayEnd && $b->ends_at > $day);

                if ($block) {
                    $busy++;
                    $cells[] = [
                        'kind' => $block->booking_id ? 'booking' : 'block',
                        'label' => $block->booking_id ? 'Бронь №'.$block->booking_id : ($block->reason ?: 'Заблокировано'),
                        'url' => $block->booking_id ? BookingResource::getUrl('edit', ['record' => $block->booking_id]) : null,
                    ];
                } elseif ($request) {
                    $cells[] = ['kind' => 'request', 'label' => 'Заявка №'.$request->id.' — '.mb_strtolower(Booking::STATUSES[$request->status] ?? ''), 'url' => BookingResource::getUrl('edit', ['record' => $request->id])];
                } else {
                    $cells[] = ['kind' => null, 'label' => null, 'url' => null];
                }
            }

            return ['car' => $car, 'cells' => $cells, 'busy' => $busy];
        });
    }

    /** Загрузка парка за видимый период, %. */
    public function utilization(Collection $rows): int
    {
        $total = $rows->count() * self::DAYS;

        return $total ? (int) round($rows->sum('busy') / $total * 100) : 0;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('block')->label('Заблокировать даты')->icon(Heroicon::OutlinedNoSymbol)
                ->schema([
                    Select::make('car_id')->label('Машина')->options(fn () => Car::query()->published()->orderBy('name')->pluck('name', 'id'))->searchable()->required(),
                    DateTimePicker::make('starts_at')->label('С')->seconds(false)->required(),
                    DateTimePicker::make('ends_at')->label('По')->seconds(false)->required()->after('starts_at'),
                    TextInput::make('reason')->label('Причина')->placeholder('Ремонт, у владельца, аренда вне сайта…'),
                ])
                ->action(function (array $data) {
                    $car = Car::query()->findOrFail($data['car_id']);
                    $conflicts = Availability::conflicts($car, Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']));
                    CarBlock::query()->create($data);
                    $note = Notification::make()->title('Даты заблокированы');
                    $conflicts->isEmpty()
                        ? $note->success()
                        : $note->warning()->body('Внимание, пересекается: '.Availability::describe($conflicts));
                    $note->send();
                }),
        ];
    }

    protected static function accessArea(): string
    {
        return 'bookings';
    }
}
