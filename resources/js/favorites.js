/**
 * Избранное (сердечко) и «Поделиться». Список id — в localStorage, страница /izbrannoe?ids=…
 */
const KEY = 'favorites:cars';
const LIMIT = 30;

const read = () => {
    try { const v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.map(Number).filter(Boolean) : []; } catch { return []; }
};
const write = (ids) => {
    try { localStorage.setItem(KEY, JSON.stringify(ids.slice(0, LIMIT))); } catch { /* приватный режим */ }
    render();
};
const url = (ids) => `/izbrannoe?ids=${ids.join(',')}`;

function toast(text) {
    let el = document.querySelector('.toast');
    if (!el) {
        el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        document.body.append(el);
    }
    el.textContent = text;
    el.classList.add('is-visible');
    clearTimeout(el.timer);
    el.timer = setTimeout(() => el.classList.remove('is-visible'), 2400);
}

function render() {
    const ids = new Set(read());
    document.querySelectorAll('[data-fav-toggle]').forEach((btn) => {
        const on = ids.has(Number(btn.dataset.favToggle));
        btn.setAttribute('aria-pressed', String(on));
        btn.classList.toggle('is-active', on);
        btn.title = on ? 'Убрать из избранного' : 'В избранное';
        btn.setAttribute('aria-label', btn.title);
    });
    document.querySelectorAll('[data-fav-link]').forEach((a) => {
        a.href = ids.size ? url([...ids]) : '/izbrannoe';
        const badge = a.querySelector('[data-fav-count]');
        if (badge) { badge.textContent = ids.size || ''; badge.hidden = !ids.size; }
    });
}

async function share(title, link) {
    if (navigator.share) {
        try { await navigator.share({ title, url: link }); return; } catch (e) { if (e.name === 'AbortError') return; }
    }
    try {
        await navigator.clipboard.writeText(link);
        toast('Ссылка скопирована');
    } catch {
        window.prompt('Скопируйте ссылку', link);
    }
}

export function initFavorites() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fav-toggle]');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const id = Number(btn.dataset.favToggle);
            const ids = read();
            const i = ids.indexOf(id);
            if (i >= 0) {
                ids.splice(i, 1);
                toast('Убрали из избранного');
            } else {
                ids.unshift(id);
                toast('Добавили в избранное');
                btn.classList.add('is-pop');
                setTimeout(() => btn.classList.remove('is-pop'), 400);
            }
            write(ids);
            return;
        }
        const sh = e.target.closest('[data-share]');
        if (sh) {
            e.preventDefault();
            share(sh.dataset.shareTitle || document.title, sh.dataset.shareUrl || location.href);
        }
    });

    const page = document.querySelector('[data-favorites-page]');
    if (page) {
        const stored = read();
        if (!page.dataset.ids && stored.length && !new URLSearchParams(location.search).has('ids')) {
            location.replace(url(stored));
            return;
        }
        page.querySelector('[data-favorites-clear]')?.addEventListener('click', () => { write([]); location.assign('/izbrannoe?ids='); });
    }

    window.addEventListener('storage', (e) => e.key === KEY && render());
    render();
}
