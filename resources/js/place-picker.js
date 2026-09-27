/**
 * Выбор точки выдачи/возврата из 200+ мест: поиск (русский, транслит, неверная раскладка),
 * группы по городам, «Популярные» сверху, цена доставки. Доступный combobox поверх обычного <select>,
 * который остаётся для работы без JS и для отправки формы.
 */
const LAYOUT_EN = "qwertyuiop[]asdfghjkl;'zxcvbnm,.`";
const LAYOUT_RU = 'йцукенгшщзхъфывапролджэячсмитьбюё';
const TRANSLIT = [
    ['shch', 'щ'], ['sch', 'щ'], ['yo', 'е'], ['zh', 'ж'], ['kh', 'х'], ['ts', 'ц'], ['ch', 'ч'], ['sh', 'ш'], ['yu', 'ю'], ['ya', 'я'], ['ye', 'е'],
    ['a', 'а'], ['b', 'б'], ['v', 'в'], ['g', 'г'], ['d', 'д'], ['e', 'е'], ['z', 'з'], ['i', 'и'], ['y', 'й'], ['k', 'к'], ['l', 'л'], ['m', 'м'],
    ['n', 'н'], ['o', 'о'], ['p', 'п'], ['r', 'р'], ['s', 'с'], ['t', 'т'], ['u', 'у'], ['f', 'ф'], ['h', 'х'], ['c', 'к'], ['w', 'в'], ['x', 'кс'], ['j', 'дж'], ['q', 'к'],
];

const norm = (s) => String(s).toLowerCase().replace(/ё/g, 'е').replace(/э/g, 'е').replace(/[^a-zа-я0-9]+/g, ' ').trim();
const swapLayout = (s) => [...s].map((ch) => { const i = LAYOUT_EN.indexOf(ch); return i >= 0 ? LAYOUT_RU[i] : ch; }).join('');
function translit(s) {
    let out = s;
    for (const [lat, cyr] of TRANSLIT) out = out.split(lat).join(cyr);
    return out;
}
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

let places = null;
function data() {
    if (places) return places;
    try { places = JSON.parse(document.getElementById('places-data')?.textContent || '[]'); } catch { places = []; }
    places.forEach((p) => { p.hay = norm(`${p.group} ${p.label} ${p.name || ''}`); });
    return places;
}

let uid = 0;

export function initPlacePickers(root = document) {
    root.querySelectorAll('select[data-place-picker]').forEach((select) => enhance(select));
}

function enhance(select) {
    if (select.dataset.enhanced) return;
    const list = data();
    if (!list.length) return;
    select.dataset.enhanced = '1';
    const id = `pp-${++uid}`;
    const byId = new Map(list.map((p) => [String(p.id), p]));
    const title = (p) => (p ? (p.label ? `${p.group}, ${p.label}` : p.group) : '');

    const wrap = document.createElement('div');
    wrap.className = 'pp';
    wrap.innerHTML = `
        <div class="pp-field">
            <svg class="pp-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            <input class="field pp-input" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="${id}-list"
                   autocomplete="off" spellcheck="false" placeholder="${esc(select.dataset.placeholder || 'Город, аэропорт, вокзал…')}">
            <svg class="pp-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div class="pp-panel" id="${id}-list" role="listbox" hidden></div>`;
    select.after(wrap);
    select.classList.add('pp-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const input = wrap.querySelector('.pp-input');
    const panel = wrap.querySelector('.pp-panel');
    const label = select.id ? document.querySelector(`label[for="${select.id}"]`) : null;
    if (label) {
        const inputId = `${select.id}-pp`;
        input.id = inputId;
        label.htmlFor = inputId;
    }
    let active = -1;
    let items = [];

    const current = () => byId.get(String(select.value));
    const showValue = () => { input.value = title(current()); };
    showValue();

    function render(query) {
        const q = norm(query || '');
        let found;
        if (!q) {
            const popular = list.filter((p) => p.popular);
            const rest = list.filter((p) => !p.popular);
            found = [...popular.map((p) => ({ ...p, section: 'Популярные' })), ...rest.map((p) => ({ ...p, section: p.group }))];
        } else {
            const variants = [...new Set([q, norm(swapLayout(q)), norm(translit(q)), norm(translit(swapLayout(q)))])];
            const words = variants.map((v) => v.split(' ').filter(Boolean));
            found = list
                .map((p) => {
                    const hit = words.find((ws) => ws.every((w) => p.hay.includes(w)));
                    if (!hit) return null;
                    const starts = hit.some((w) => norm(p.group).startsWith(w)) ? 0 : 1;
                    return { ...p, section: p.group, rank: (p.popular ? 0 : 2) + starts };
                })
                .filter(Boolean)
                .sort((a, b) => a.rank - b.rank)
                .slice(0, 60);
        }
        items = found;
        active = found.findIndex((p) => String(p.id) === String(select.value));
        if (!found.length) {
            panel.innerHTML = `<p class="pp-empty">Не нашли «${esc(query)}». Напишите нам — доставим по любому адресу в Крыму.</p>`;
            return;
        }
        let html = '';
        let section = null;
        found.forEach((p, i) => {
            if (p.section !== section) {
                section = p.section;
                html += `<p class="pp-group" role="presentation">${esc(section)}</p>`;
            }
            const text = p.label ? (section === 'Популярные' ? `${p.group}, ${p.label}` : p.label) : p.group;
            html += `<div class="pp-option${i === active ? ' is-active' : ''}" role="option" id="${id}-o${i}" data-i="${i}" aria-selected="${String(p.id) === String(select.value)}">
                <span>${esc(text)}</span><small>${esc(p.price)}</small></div>`;
        });
        panel.innerHTML = html;
        highlight();
    }

    function highlight() {
        panel.querySelectorAll('.pp-option').forEach((el) => el.classList.toggle('is-active', Number(el.dataset.i) === active));
        const el = panel.querySelector(`[data-i="${active}"]`);
        if (el) {
            input.setAttribute('aria-activedescendant', el.id);
            el.scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function open() {
        if (!panel.hidden) return;
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        wrap.classList.add('is-open');
        render('');
        input.select();
    }

    function close(restore = true) {
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        wrap.classList.remove('is-open');
        if (restore) showValue();
    }

    function choose(i) {
        const p = items[i];
        if (!p) return;
        select.value = String(p.id);
        select.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }

    input.addEventListener('focus', open);
    input.addEventListener('click', open);
    input.addEventListener('input', () => { if (panel.hidden) open(); render(input.value); active = items.length ? 0 : -1; highlight(); });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (panel.hidden) open();
            active = Math.max(0, Math.min(items.length - 1, active + (e.key === 'ArrowDown' ? 1 : -1)));
            highlight();
        } else if (e.key === 'Enter' && !panel.hidden) {
            e.preventDefault();
            choose(active >= 0 ? active : 0);
        } else if (e.key === 'Escape' && !panel.hidden) {
            e.preventDefault();
            close();
        } else if (e.key === 'Tab' && !panel.hidden) {
            close();
        }
    });
    panel.addEventListener('mousedown', (e) => e.preventDefault()); // не терять фокус поля
    panel.addEventListener('click', (e) => {
        const opt = e.target.closest('.pp-option');
        if (opt) choose(Number(opt.dataset.i));
    });
    document.addEventListener('pointerdown', (e) => { if (!wrap.contains(e.target)) close(); });
    select.addEventListener('change', showValue);
}
