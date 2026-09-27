/** Карта грузится по кнопке «Показать карту»; вкладки точек меняют адрес карты. */
export function initMaps() {
    document.querySelectorAll('.map-block').forEach((block) => {
        const frame = block.querySelector('[data-map]');
        const load = (src) => {
            frame.dataset.src = src;
            frame.innerHTML = `<iframe src="${src}" title="Карта" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>`;
            frame.classList.add('is-loaded');
        };
        block.querySelector('[data-map-load]')?.addEventListener('click', () => load(frame.dataset.src));
        block.querySelectorAll('[data-map-point]').forEach((btn) => btn.addEventListener('click', () => {
            block.querySelectorAll('[data-map-point]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
            const src = btn.dataset.mapPoint;
            const text = new URL(src).searchParams.get('text');
            const open = block.querySelector('[data-map-open]');
            if (open && text) open.href = `https://yandex.ru/maps/?text=${encodeURIComponent(text)}`;
            if (frame.classList.contains('is-loaded')) load(src); else frame.dataset.src = src;
        }));
    });
}
