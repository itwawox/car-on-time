/**
 * Сравнение машин: список id в localStorage (до 3), кнопки «Сравнить» на карточках,
 * плавающая панель со ссылкой на /sravnenie?ids=…
 */
const KEY = 'compare:cars';
const LIMIT = 3;

function read() {
    try {
        const list = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(list) ? list.filter((c) => c && c.id).slice(0, LIMIT) : [];
    } catch {
        return [];
    }
}

function write(list) {
    try { localStorage.setItem(KEY, JSON.stringify(list.slice(0, LIMIT))); } catch { /* приватный режим */ }
    render();
}

const url = (list) => `/sravnenie?ids=${list.map((c) => c.id).join(',')}`;

let bar;
let toast;

function render() {
    const list = read();
    const ids = new Set(list.map((c) => String(c.id)));

    document.querySelectorAll('[data-compare-toggle]').forEach((btn) => {
        const on = ids.has(btn.dataset.compareToggle);
        btn.setAttribute('aria-pressed', String(on));
        btn.classList.toggle('is-active', on);
        const label = btn.querySelector('.compare-toggle-label');
        if (label) label.textContent = on ? 'В сравнении' : 'Сравнить';
        btn.title = on ? 'Убрать из сравнения' : 'Сравнить';
        btn.setAttribute('aria-label', btn.title);
    });

    if (document.querySelector('[data-compare-page]')) return; // на самой странице сравнения панель не нужна

    if (!list.length) {
        bar?.classList.remove('is-open');
        return;
    }
    if (!bar) {
        bar = document.createElement('div');
        bar.className = 'compare-bar';
        bar.setAttribute('role', 'region');
        bar.setAttribute('aria-label', 'Сравнение');
        document.body.append(bar);
        bar.addEventListener('click', (e) => {
            if (e.target.closest('[data-compare-bar-clear]')) write([]);
        });
    }
    bar.innerHTML = `
        <span class="compare-bar-count">Сравнение: <b>${list.length}</b> из ${LIMIT}</span>
        <span class="compare-bar-names">${list.map((c) => escapeHtml(c.name)).join(' · ')}</span>
        <button type="button" class="compare-bar-clear" data-compare-bar-clear aria-label="Очистить сравнение">Очистить</button>
        <a class="btn btn-primary btn-sm" href="${url(list)}">${list.length > 1 ? 'Сравнить' : 'Добавьте ещё'}</a>`;
    requestAnimationFrame(() => bar.classList.add('is-open'));
}

function notify(text) {
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        document.body.append(toast);
    }
    toast.textContent = text;
    toast.classList.add('is-visible');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => toast.classList.remove('is-visible'), 2600);
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

export function initCompare() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-compare-toggle]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const list = read();
        const id = btn.dataset.compareToggle;
        const index = list.findIndex((c) => String(c.id) === id);
        if (index >= 0) {
            list.splice(index, 1);
        } else {
            if (list.length >= LIMIT) {
                notify(`Сравнить можно до ${LIMIT} машин — уберите одну из списка`);
                return;
            }
            list.push({ id: Number(id), name: btn.dataset.compareName || '' });
        }
        write(list);
    });

    const page = document.querySelector('[data-compare-page]');
    if (page) {
        const stored = read();
        const pageIds = (page.dataset.ids || '').split(',').filter(Boolean);
        // Пришли без ?ids — подставляем сохранённый список
        if (!pageIds.length && stored.length) {
            location.replace(url(stored));
            return;
        }
        page.addEventListener('click', (e) => {
            const remove = e.target.closest('[data-compare-remove]');
            if (remove) {
                const next = read().filter((c) => String(c.id) !== remove.dataset.compareRemove);
                write(next);
                location.assign(next.length ? url(next) : '/sravnenie?ids=');
            }
            if (e.target.closest('[data-compare-clear]')) {
                write([]);
                location.assign('/sravnenie?ids=');
            }
        });
        page.querySelector('[data-compare-diff]')?.addEventListener('change', (e) => {
            page.classList.toggle('only-diff', e.target.checked);
        });
    }

    window.addEventListener('storage', (e) => e.key === KEY && render());
    render();
}
