/**
 * Выбор периода аренды: календарь диапазона (1 месяц на телефоне, 2 на компьютере) + время выдачи и возврата.
 * Пишет значения в исходные поля datetime-local (они остаются для работы без JS) и шлёт им change —
 * калькулятор цены пересчитывает сумму как обычно.
 */
const MONTHS = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
const MONTHS_GEN = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
const WEEKDAYS = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const pad = (n) => String(n).padStart(2, '0');
const day = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const same = (a, b) => a && b && a.getTime() === b.getTime();
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const diffDays = (a, b) => Math.round((day(b) - day(a)) / 86400000);
const human = (d) => `${d.getDate()} ${MONTHS_GEN[d.getMonth()]}`;

function parse(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value || '');
    return m ? { date: new Date(+m[1], +m[2] - 1, +m[3]), time: `${m[4]}:${m[5]}` } : null;
}

function plural(n, forms) {
    const a = Math.abs(n) % 100;
    const b = a % 10;
    if (a > 10 && a < 20) return forms[2];
    if (b > 1 && b < 5) return forms[1];
    if (b === 1) return forms[0];
    return forms[2];
}

const MEMORY_KEY = 'trip:dates';
const MEMORY_TTL = 7 * 86400000;

/** Даты, выбранные на любой странице, живут 7 дней и подставляются в календари и калькулятор. */
export function rememberedDates() {
    try {
        const saved = JSON.parse(localStorage.getItem(MEMORY_KEY) || 'null');
        if (!saved || Date.now() - saved.savedAt > MEMORY_TTL) return null;
        const start = parse(saved.start);
        if (!start || start.date < day(new Date())) return null;
        return saved;
    } catch {
        return null;
    }
}

function remember(start, end) {
    try { localStorage.setItem(MEMORY_KEY, JSON.stringify({ start, end, savedAt: Date.now() })); } catch { /* приватный режим */ }
}

export function initDateRange(root) {
    const startInput = root.querySelector('[name="starts_at"]');
    const endInput = root.querySelector('[name="ends_at"]');
    if (!startInput || !endInput) return;

    const compact = 'compact' in root.dataset;
    // Время выдачи и возврата — внизу всплывающего календаря, а на поле — «28 сен, 10:00 — 1 окт, 10:00 · 3 суток»
    const popoverTimes = root.dataset.times === 'popover';
    // Даты из ссылки (форма на главной) важнее запомненных
    const params = new URLSearchParams(location.search);
    if (params.get('starts_at') && params.get('ends_at') && parse(params.get('starts_at')) && parse(params.get('ends_at'))) {
        remember(params.get('starts_at'), params.get('ends_at'));
    }
    const saved = rememberedDates();
    if (saved) {
        startInput.value = saved.start;
        endInput.value = saved.end;
    }
    let empty = !startInput.value;

    const minDays = Math.max(1, Number(root.dataset.minDays) || 1);
    const today = day(new Date());
    const s = parse(startInput.value);
    const e = parse(endInput.value);
    let start = s?.date ?? addDays(today, 1);
    let end = e?.date ?? addDays(start, Math.max(3, minDays));
    let startTime = s?.time ?? '10:00';
    let endTime = e?.time ?? '10:00';
    let picking = null; // null — выбран диапазон; 'end' — выбрано начало, ждём конец
    let hover = null;
    let view = new Date(start.getFullYear(), start.getMonth(), 1);

    const times = [];
    for (let h = 0; h < 24; h++) for (const m of ['00', '30']) times.push(`${pad(h)}:${m}`);
    const timeOptions = (sel) => times.map((t) => `<option value="${t}"${t === sel ? ' selected' : ''}>${t}</option>`).join('');

    function timesHtml(hidden, extra = '') {
        return `<div class="daterange-times ${extra}"${hidden ? ' hidden' : ''}>
            <label><span class="field-label">Выдача</span><select class="field" data-time="start" aria-label="Время выдачи">${timeOptions(startTime)}</select></label>
            <label><span class="field-label">Возврат</span><select class="field" data-time="end" aria-label="Время возврата">${timeOptions(endTime)}</select></label>
        </div>`;
    }

    root.classList.add('is-enhanced');
    const ui = document.createElement('div');
    ui.className = 'daterange';
    ui.innerHTML = `
        <span class="field-label${compact ? ' sr-only' : ''}" id="dr-label">Даты аренды</span>
        <button type="button" class="field daterange-trigger" aria-haspopup="dialog" aria-expanded="false" aria-labelledby="dr-label dr-value">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
            <span id="dr-value" data-value></span>
        </button>
        ${popoverTimes ? '' : timesHtml(compact)}
        <div class="daterange-pop" role="dialog" aria-label="Выбор дат аренды" hidden>
            <div class="daterange-head">
                <button type="button" class="daterange-nav" data-nav="-1" aria-label="Предыдущий месяц"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 6-6 6 6 6"/></svg></button>
                <p class="daterange-hint" data-hint aria-live="polite"></p>
                <button type="button" class="daterange-nav" data-nav="1" aria-label="Следующий месяц"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg></button>
            </div>
            <div class="daterange-months" data-months></div>
            ${popoverTimes ? timesHtml(false, 'daterange-times-pop') : ''}
            <div class="daterange-foot">
                <span class="note" data-summary></span>
                <button type="button" class="btn btn-primary btn-sm" data-done>Готово</button>
            </div>
        </div>`;
    root.prepend(ui);

    const trigger = ui.querySelector('.daterange-trigger');
    const pop = ui.querySelector('.daterange-pop');
    const months = ui.querySelector('[data-months]');

    function sync() {
        empty = false;
        startInput.value = `${iso(start)}T${startTime}`;
        endInput.value = `${iso(end)}T${endTime}`;
        remember(startInput.value, endInput.value);
        endInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function label() {
        if (empty) {
            ui.querySelector('[data-value]').textContent = root.dataset.placeholder || 'Цены за ваши даты';
            return;
        }
        const days = diffDays(start, end);
        const span = `${days} ${plural(days, ['сутки', 'суток', 'суток'])}`;
        // В режиме popover срок уже виден в цене рядом («за 3 суток») — в поле только даты и время, в одну строку
        ui.querySelector('[data-value]').textContent = popoverTimes
            ? `${human(start)}, ${startTime} — ${human(end)}, ${endTime}`
            : `${human(start)} — ${human(end)} · ${span}`;
    }

    function monthHtml(first) {
        const offset = (first.getDay() + 6) % 7;
        const total = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
        const rangeEnd = picking === 'end' ? hover : end;
        let cells = '';
        for (let i = 0; i < offset; i++) cells += '<span></span>';
        for (let d = 1; d <= total; d++) {
            const date = new Date(first.getFullYear(), first.getMonth(), d);
            const disabled = date < today || (picking === 'end' && date < start);
            const tooShort = picking === 'end' && !disabled && diffDays(start, date) < minDays;
            const cls = [
                'daterange-day',
                same(date, start) && 'is-start',
                rangeEnd && same(date, rangeEnd) && 'is-end',
                rangeEnd && date > start && date < rangeEnd && 'is-in',
                same(date, today) && 'is-today',
                tooShort && 'is-short',
            ].filter(Boolean).join(' ');
            cells += `<button type="button" class="${cls}" data-date="${iso(date)}" ${disabled || tooShort ? 'disabled' : ''} aria-label="${d} ${MONTHS_GEN[date.getMonth()]}" aria-pressed="${same(date, start) || same(date, end)}">${d}</button>`;
        }
        return `<div class="daterange-month">
            <p class="daterange-title">${MONTHS[first.getMonth()]} ${first.getFullYear()}</p>
            <div class="daterange-grid">${WEEKDAYS.map((w) => `<span class="daterange-wd">${w}</span>`).join('')}${cells}</div>
        </div>`;
    }

    function draw() {
        const count = window.matchMedia('(min-width: 640px)').matches ? 2 : 1;
        let html = '';
        for (let i = 0; i < count; i++) html += monthHtml(new Date(view.getFullYear(), view.getMonth() + i, 1));
        months.innerHTML = html;
        ui.querySelector('[data-nav="-1"]').disabled = view <= new Date(today.getFullYear(), today.getMonth(), 1);
        ui.querySelector('[data-hint]').textContent = picking === 'end'
            ? `Выберите дату возврата${minDays > 1 ? ` · минимум ${minDays} ${plural(minDays, ['сутки', 'суток', 'суток'])}` : ''}`
            : 'Выберите дату выдачи';
        const days = diffDays(start, end);
        ui.querySelector('[data-summary]').textContent = picking === 'end' ? `С ${human(start)}` : `${human(start)} — ${human(end)}, ${days} ${plural(days, ['сутки', 'суток', 'суток'])}`;
        ui.querySelector('[data-done]').disabled = picking === 'end';
    }

    function open() {
        pop.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        picking = null;
        view = new Date(start.getFullYear(), start.getMonth(), 1);
        draw();
        (months.querySelector('.is-start') || months.querySelector('button:not([disabled])'))?.focus({ preventScroll: true });
    }

    function close(focus = true) {
        if (pop.hidden) return;
        if (picking === 'end') { // не довыбрали — возвращаем минимальный срок
            picking = null;
            if (!empty) {
                end = addDays(start, Math.max(3, minDays));
                sync();
                label();
            }
        }
        pop.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (focus) trigger.focus({ preventScroll: true });
    }

    trigger.addEventListener('click', () => (pop.hidden ? open() : close()));
    ui.querySelector('[data-done]').addEventListener('click', () => close());
    ui.querySelectorAll('[data-nav]').forEach((b) => b.addEventListener('click', () => {
        view = new Date(view.getFullYear(), view.getMonth() + Number(b.dataset.nav), 1);
        draw();
    }));
    months.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-date]');
        if (!btn || btn.disabled) return;
        const [y, m, d] = btn.dataset.date.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        if (picking === 'end') {
            end = date;
            picking = null;
            sync();
            label();
            draw();
            if (popoverTimes) ui.querySelector('[data-time="start"]')?.focus({ preventScroll: true });
            else setTimeout(() => close(), 250);
        } else {
            start = date;
            picking = 'end';
            hover = addDays(start, minDays);
            draw();
            months.querySelector(`[data-date="${iso(hover)}"]`)?.focus({ preventScroll: true });
        }
    });
    months.addEventListener('mouseover', (ev) => {
        const btn = ev.target.closest('[data-date]');
        if (picking !== 'end' || !btn || btn.disabled) return;
        const [y, m, d] = btn.dataset.date.split('-').map(Number);
        const date = new Date(y, m - 1, d);
        if (!same(date, hover)) {
            hover = date;
            draw();
        }
    });
    ui.querySelectorAll('[data-time]').forEach((select) => select.addEventListener('change', () => {
        if (select.dataset.time === 'start') startTime = select.value;
        else endTime = select.value;
        sync();
        label();
    }));
    document.addEventListener('pointerdown', (ev) => {
        if (!pop.hidden && !ui.contains(ev.target)) close(false);
    });
    pop.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') { ev.preventDefault(); close(); return; }
        const cur = document.activeElement?.dataset?.date;
        const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[ev.key];
        if (!cur || !step) return;
        ev.preventDefault();
        const [y, m, d] = cur.split('-').map(Number);
        const next = addDays(new Date(y, m - 1, d), step);
        if (next < today) return;
        if (next < view || next >= new Date(view.getFullYear(), view.getMonth() + 2, 1)) {
            view = new Date(next.getFullYear(), next.getMonth(), 1);
            draw();
        }
        months.querySelector(`[data-date="${iso(next)}"]`)?.focus();
    });

    // Если по умолчанию срок меньше минимального — поправляем
    if (!empty && diffDays(start, end) < minDays) {
        end = addDays(start, minDays);
        endInput.value = `${iso(end)}T${endTime}`;
    }
    label();
    // Даты пришли из памяти — сообщаем странице (каталог пересчитает цены)
    if (!empty && (saved || compact)) endInput.dispatchEvent(new Event('change', { bubbles: true }));
}
