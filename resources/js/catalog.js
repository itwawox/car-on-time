/**
 * Каталог: «Карточки | Подробно» (выбор запоминается) и цены за выбранные даты для машин на странице.
 */
const VIEW_KEY = 'catalog:view';

const fmt = (n) => Math.round(n).toLocaleString('ru-RU');
const plural = (n, forms) => {
    const a = Math.abs(n) % 100;
    const b = a % 10;
    if (a > 10 && a < 20) return forms[2];
    if (b > 1 && b < 5) return forms[1];
    return b === 1 ? forms[0] : forms[2];
};

// Вводный текст раздела — одной строкой; «Подробнее» показываем, только если текст действительно обрезан
function initLead() {
    const lead = document.querySelector('[data-lead]');
    const more = document.querySelector('[data-lead-more]');
    if (!lead || !more) return;
    more.hidden = lead.scrollHeight <= lead.clientHeight + 1;
    more.addEventListener('click', () => {
        lead.classList.add('is-open');
        more.hidden = true;
    });
}

export function initCatalog() {
    initLead();
    const views = document.querySelector('[data-catalog-views]');
    if (!views) return;

    const root = document.documentElement;
    const buttons = document.querySelectorAll('[data-view-set]');
    const apply = (view) => {
        root.dataset.catalogView = view;
        buttons.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.viewSet === view)));
    };
    apply(root.dataset.catalogView === 'table' ? 'table' : 'cards');
    buttons.forEach((b) => b.addEventListener('click', () => {
        apply(b.dataset.viewSet);
        try { localStorage.setItem(VIEW_KEY, b.dataset.viewSet); } catch { /* приватный режим */ }
    }));

    // Цены за даты
    const dates = document.querySelector('[data-catalog-dates]');
    const ids = views.dataset.ids;
    if (!dates || !ids) return;
    const start = dates.querySelector('[name="starts_at"]');
    const end = dates.querySelector('[name="ends_at"]');
    const original = new Map();
    document.querySelectorAll('[data-card-price]').forEach((el) => original.set(el, el.innerHTML));
    let controller;

    // Выбранная на главной точка выдачи — плашка «📍 Алушта, автовокзал ×»
    const chip = document.querySelector('[data-place-chip]');
    const renderChip = () => {
        let place = null;
        try { place = JSON.parse(localStorage.getItem('trip:place') || 'null'); } catch { place = null; }
        let places = [];
        try { places = JSON.parse(document.getElementById('places-data')?.textContent || '[]'); } catch { places = []; }
        const p = place && places.find((x) => String(x.id) === String(place.from));
        if (!chip) return;
        chip.hidden = !p;
        if (p) chip.querySelector('[data-place-chip-text]').textContent = p.label ? `${p.group}, ${p.label}` : p.group;
    };
    chip?.querySelector('[data-place-chip-clear]')?.addEventListener('click', () => {
        try { localStorage.removeItem('trip:place'); } catch { /* приватный режим */ }
        renderChip();
        end.dispatchEvent(new Event('change'));
    });
    renderChip();

    end.addEventListener('change', async () => {
        if (!start.value || !end.value) return;
        controller?.abort();
        controller = new AbortController();
        document.querySelectorAll('[data-card-price]').forEach((el) => el.classList.add('is-updating'));
        try {
            const params = new URLSearchParams({ ids, starts_at: start.value, ends_at: end.value });
            let place = null;
            try { place = JSON.parse(localStorage.getItem('trip:place') || 'null'); } catch { place = null; }
            if (place?.from) params.set('pickup_location_id', place.from);
            if (place?.to) params.set('return_location_id', place.to);
            const res = await fetch(`/quote/batch?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = await res.json();
            document.querySelectorAll('[data-card-price]').forEach((el) => {
                el.classList.remove('is-updating');
                const q = data.prices?.[el.dataset.cardPrice];
                const card = el.closest('.car-card, [role="row"]');
                card?.classList.toggle('is-busy', q?.available === false);
                if (q?.ok) {
                    const delivery = q.delivery ? ` + доставка ${fmt(q.delivery)} ₽` : '';
                    el.innerHTML = `${fmt(q.total + (q.delivery || 0))} ₽ <small>за ${q.days} ${plural(q.days, ['сутки', 'суток', 'суток'])}${delivery ? ' с доставкой' : ''}</small>`;
                    el.title = `Аренда ${fmt(q.total)} ₽${delivery}`;
                    el.classList.add('is-period');
                    if (q.available === false) el.insertAdjacentHTML('beforeend', `<small class="price-note is-busy">${dates.dataset.busyText || 'Занята на эти даты'}</small>`);
                } else {
                    el.innerHTML = original.get(el) + (q?.error ? `<small class="price-note">${q.error}</small>` : '');
                    el.classList.remove('is-period');
                }
            });
        } catch (e) {
            if (e.name !== 'AbortError') document.querySelectorAll('[data-card-price]').forEach((el) => el.classList.remove('is-updating'));
        }
    });
}
