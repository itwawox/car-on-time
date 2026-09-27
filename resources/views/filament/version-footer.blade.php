@auth
    @php($version = \App\Support\AppVersion::current())
    <div style="padding: 12px 24px; font-size: .75rem; opacity: .6; text-align: center;">
        @if($version)
            Версия {{ $version['short'] }}
            @if($version['branch']) · ветка {{ $version['branch'] }}@endif
            @if($version['deployedAt']) · выложена {{ $version['deployedAt']->format('d.m.Y H:i') }}@endif
            @if($version['compareUrl'])
                · <a href="{{ $version['compareUrl'] }}" target="_blank" rel="noopener" style="text-decoration: underline;">сравнить с GitHub</a>
            @endif
        @else
            Версия не записана — это копия на компьютере или сайт выложен не через deploy/release.sh
        @endif
    </div>
@endauth
