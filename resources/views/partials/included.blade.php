{{-- «Что входит в аренду / Оплачивается отдельно» + способы оплаты --}}
@php($inc = \App\Support\Trust::included($car ?? null))
@php($payments = \App\Support\Trust::payments())
<section class="included" aria-labelledby="h-included">
    <h2 id="h-included">{{ \App\Models\Setting::get('included_title') ?: 'Что входит в аренду' }}</h2>
    <div class="included-grid">
        <ul class="included-list is-yes">
            @foreach($inc['included'] as $line)
                <li>@include('partials.icon', ['name' => 'check', 'size' => 16, 'stroke' => 2.4]) {{ $line }}</li>
            @endforeach
        </ul>
        <div>
            <p class="included-sub">{{ \App\Models\Setting::get('not_included_title') ?: 'Оплачивается отдельно' }}</p>
            <ul class="included-list is-extra">
                @foreach($inc['extra'] as $line)
                    <li>@include('partials.icon', ['name' => 'wallet', 'size' => 16]) {{ $line }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @if($payments)
        <p class="payments"><span>{{ \App\Models\Setting::get('payment_title') ?: 'Оплата при получении:' }}</span> @foreach($payments as $p)<span class="payment">{{ $p }}</span>@endforeach</p>
    @endif
</section>
