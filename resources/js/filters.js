/**
 * Панель фильтров каталога: открытие/закрытие, двойной ползунок цены, поиск марки,
 * живой счётчик «Показать N авто» (/katalog/count). Применение — обычная GET-форма.
 */
const plural = (n, forms) => {
    const a = Math.abs(n) % 100;
    const b = a % 10;
    if (a > 10 && a < 20) return forms[2];
    if (b > 1 && b < 5) return forms[1];
    return b === 1 ? forms[0] : forms[2];
};

export function initFilters() {
    const dialog = document.querySelector('[data-filters]');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const submit = dialog.querySelector('[data-filters-submit]');
    const url = dialog.dataset.countUrl;

    document.querySelectorAll('[data-filters-open]').forEach((b) => b.addEventListener('click', () => {
        dialog.showModal();
        document.documentElement.classList.add('lightbox-open');
        count();
    }));
    const close = () => dialog.close();
    dialog.querySelector('[data-filters-close]')?.addEventListener('click', close);
    dialog.addEventListener('close', () => document.documentElement.classList.remove('lightbox-open'));
    dialog.addEventListener('click', (e) => { if (e.target === dialog) close(); });

    // Цена: ползунки ⇄ поля
    const range = dialog.querySelector('[data-range]');
    const from = range?.querySelector('[data-range-from]');
    const to = range?.querySelector('[data-range-to]');
    const minInput = form.querySelector('[name="price_min"]');
    const maxInput = form.querySelector('[name="price_max"]');
    const min = Number(range?.dataset.min || 0);
    const max = Number(range?.dataset.max || 0);
    const paint = () => {
        if (!range) return;
        const a = ((Number(from.value) - min) / (max - min || 1)) * 100;
        const b = ((Number(to.value) - min) / (max - min || 1)) * 100;
        range.style.setProperty('--a', `${a}%`);
        range.style.setProperty('--b', `${b}%`);
    };
    const fromSliders = () => {
        if (Number(from.value) > Number(to.value)) [from.value, to.value] = [to.value, from.value];
        minInput.value = Number(from.value) > min ? from.value : '';
        maxInput.value = Number(to.value) < max ? to.value : '';
        paint();
    };
    from?.addEventListener('input', fromSliders);
    to?.addEventListener('input', fromSliders);
    minInput?.addEventListener('input', () => { from.value = minInput.value || min; paint(); });
    maxInput?.addEventListener('input', () => { to.value = maxInput.value || max; paint(); });
    paint();

    // Поиск марки
    const brandSearch = dialog.querySelector('[data-brand-search]');
    brandSearch?.addEventListener('input', () => {
        const q = brandSearch.value.trim().toLowerCase();
        dialog.querySelectorAll('[data-brand]').forEach((el) => { el.hidden = q && !el.dataset.brand.includes(q) && !el.querySelector('input').checked; });
    });

    // Живой счётчик
    let timer;
    let controller;
    function count() {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            const params = new URLSearchParams(new FormData(form));
            [...params.keys()].forEach((k) => { if (!params.get(k)) params.delete(k); });
            submit.classList.add('is-counting');
            try {
                const res = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const data = await res.json();
                submit.textContent = data.count ? `Показать ${data.count} ${plural(data.count, ['авто', 'авто', 'авто'])}` : 'Нет машин — ослабьте фильтры';
                submit.disabled = !data.count;
            } catch (e) {
                if (e.name !== 'AbortError') submit.textContent = 'Показать';
            } finally {
                submit.classList.remove('is-counting');
            }
        }, 220);
    }
    form.addEventListener('input', count);
    form.addEventListener('change', count);

    // Пустые поля не отправляем — адрес короче и понятнее
    form.addEventListener('submit', () => {
        form.querySelectorAll('input, select').forEach((el) => {
            if ((el.type === 'radio' || el.type === 'checkbox') ? !el.checked : !el.value) el.disabled = true;
            if (el.type === 'range' || el.type === 'search') el.disabled = true;
        });
    });
}
