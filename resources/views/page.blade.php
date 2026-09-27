@extends('layouts.app', [
    'title' => $page->seo_title ?: $page->title,
    'description' => $page->seo_description,
    'ogType' => 'article',
    'jsonld' => !empty($crumbs) ? [app(\App\Services\Seo::class)->breadcrumbs($crumbs)] : [],
])

@section('content')
<section class="page-head">
    <div class="container-x">
        @include('partials.breadcrumbs')
        <h1>{{ $page->h1 ?: $page->title }}</h1>
    </div>
</section>

<section class="section" style="padding-top:24px">
    <div class="container-x">
        @if($page->slug === 'kontakty')
            {{-- Контакты и так на всю ширину: карточки с телефоном и адресом --}}
            @include('partials.contacts-card')
            <article class="card card-pad prose-body">
                {!! \App\Support\CmsHtml::clean($page->content) !!}
            </article>
            @include('partials.map-embed', ['id' => 'contacts', 'points' => array_filter([
                \App\Models\Setting::get('address') ? ['label' => 'Офис', 'query' => \App\Models\Setting::get('address'), 'url' => \App\Models\Setting::get('map_contacts')] : null,
                \App\Models\Setting::get('pickup_point') ? ['label' => 'Стойка в аэропорту', 'query' => 'Аэропорт Симферополь'] : null,
            ])])
        @else
            <div class="page-layout">
                <div class="page-main" style="max-width:none">
                    <article class="card card-pad prose-body">
                        {!! \App\Support\CmsHtml::clean($page->content) !!}
                    </article>
                    @if($page->slug === 'yurlicam')
                        @include('partials.lead-form')
                    @elseif($page->slug === 'sdat-avto')
                        @include('partials.owner-form')
                    @endif
                </div>
                @include('partials.help-aside')
            </div>
        @endif
    </div>
</section>
@endsection
