{{-- Отдельное согласие на обработку ПДн (152-ФЗ в ред. с 01.09.2025): своя галочка, не отмечена заранее --}}
<label class="consent" for="{{ $id }}">
    <input type="checkbox" id="{{ $id }}" name="pd_consent" value="1" required @checked(old('pd_consent'))
           data-required-text="{{ \App\Models\Setting::get('consent_required_text') ?: 'Отметьте согласие — без него мы не можем принять заявку' }}">
    <span>{{ $prefix ?? 'Даю' }} <a href="{{ route('page', 'soglasie-pdn') }}" target="_blank">согласие на обработку персональных данных</a> и принимаю <a href="{{ route('page', 'politika-konfidencialnosti') }}" target="_blank">политику</a></span>
</label>
@error('pd_consent')<p class="field-error" role="alert">{{ $message }}</p>@enderror
