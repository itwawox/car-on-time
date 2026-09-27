{{-- Звёзды оценки: $value (0–5, можно дробное), $size --}}
@php($value = (float) ($value ?? 0))
<span class="stars" style="--stars: {{ max(0, min(5, $value)) / 5 * 100 }}%; --star-size: {{ $size ?? 16 }}px" role="img" aria-label="Оценка {{ rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',') }} из 5"></span>
