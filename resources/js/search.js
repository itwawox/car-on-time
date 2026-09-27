// Умный поиск: выпадающие подсказки (combobox по WAI-ARIA), палитра ⌘K / Ctrl K / «/»,
// недавние запросы в localStorage. Вся разметка собирается через DOM API без innerHTML.

const RECENT_KEY = 'cot:recent-searches';

// Тексты и параметры из админки (Умный поиск → Настройки), см. layouts/app.blade.php
const config = (() => {
    try { return JSON.parse(document.getElementById('search-config')?.textContent || '{}'); } catch { return {}; }
})();
const t = (key, replace = {}) => Object.entries(replace).reduce((s, [k, v]) => s.replace(`:${k}`, v), String(config[key] ?? ''));
const MIN_LENGTH = Number(config.min_query_length) || 2;
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

const icons = {
    search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    recent: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    tag: '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
    car: '<path d="M5 17h14M5 17a2 2 0 1 1-4 0v-4l2-6h18l2 6v4a2 2 0 1 1-4 0"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
};

function svg(name, size = 18) {
    const el = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    el.setAttribute('width', size);
    el.setAttribute('height', size);
    el.setAttribute('viewBox', '0 0 24 24');
    el.setAttribute('fill', 'none');
    el.setAttribute('stroke', 'currentColor');
    el.setAttribute('stroke-width', '1.8');
    el.setAttribute('stroke-linecap', 'round');
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('aria-hidden', 'true');
    el.innerHTML = icons[name]; // статичная константа, не пользовательские данные
    return el;
}

function h(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = text;
    return el;
}

const recent = {
    get() {
        try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]').slice(0, 5); } catch { return []; }
    },
    add(q) {
        q = q.trim();
        if (q.length < 2) return;
        try {
            const list = [q, ...recent.get().filter((x) => x.toLowerCase() !== q.toLowerCase())].slice(0, 5);
            localStorage.setItem(RECENT_KEY, JSON.stringify(list));
        } catch { /* приватный режим */ }
    },
    clear() {
        try { localStorage.removeItem(RECENT_KEY); } catch { /* */ }
    },
};

// Подсветка совпадения в названии (только когда подстрока есть буквально)
function highlight(text, query) {
    const frag = document.createDocumentFragment();
    const words = query.toLowerCase().split(/\s+/).filter((w) => w.length >= 2);
    const lower = text.toLowerCase();
    let ranges = [];
    words.forEach((w) => {
        let i = lower.indexOf(w);
        while (i !== -1) { ranges.push([i, i + w.length]); i = lower.indexOf(w, i + w.length); }
    });
    ranges.sort((a, b) => a[0] - b[0]);
    ranges = ranges.reduce((acc, r) => {
        const last = acc[acc.length - 1];
        if (last && r[0] <= last[1]) last[1] = Math.max(last[1], r[1]); else acc.push(r);
        return acc;
    }, []);

    let pos = 0;
    ranges.forEach(([s, e]) => {
        if (s > pos) frag.append(text.slice(pos, s));
        frag.append(h('mark', null, text.slice(s, e)));
        pos = e;
    });
    frag.append(text.slice(pos));
    return frag;
}

const priceFmt = (n) => Math.round(n).toLocaleString('ru-RU') + ' ₽';

let popularCache = null;

class SearchBox {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('.sbox-input');
        this.panel = root.querySelector('.sbox-panel');
        this.clearBtn = root.querySelector('[data-search-clear]');
        this.suggestUrl = root.dataset.suggestUrl;
        this.searchUrl = root.dataset.searchUrl;
        this.form = root.tagName === 'FORM' ? root : root.closest('form');
        this.active = -1;
        this.options = [];
        this.timer = null;
        this.controller = null;
        this.lastQuery = null;
        this.uid = this.input.id || `sbox-${Math.random().toString(36).slice(2)}`;

        this.bind();
        this.syncClear();
    }

    bind() {
        this.input.addEventListener('input', () => {
            this.syncClear();
            clearTimeout(this.timer);
            clearTimeout(this.finalTimer);
            this.timer = setTimeout(() => this.update(), 120);
            // Пауза в наборе — запрос «законченный», пишем в журнал поиска
            this.finalTimer = setTimeout(() => this.logFinal(), 1500);
        });
        this.input.addEventListener('focus', () => this.update());
        this.input.addEventListener('keydown', (e) => this.onKey(e));
        this.clearBtn?.addEventListener('click', () => {
            this.input.value = '';
            this.syncClear();
            this.input.focus();
            this.update();
        });

        this.form?.addEventListener('submit', () => recent.add(this.input.value));

        document.addEventListener('pointerdown', (e) => {
            if (!this.root.contains(e.target)) this.close();
        });

        // Подсказки открываются по клику, запоминаем запрос
        this.panel.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (link && this.input.value.trim()) recent.add(this.input.value);
            const clear = e.target.closest('[data-recent-clear]');
            if (clear) {
                e.preventDefault();
                recent.clear();
                this.update(true);
            }
            const fill = e.target.closest('[data-fill]');
            if (fill) {
                e.preventDefault();
                this.input.value = fill.dataset.fill;
                this.syncClear();
                this.input.focus();
                this.update(true);
            }
        });
    }

    logFinal() {
        const q = this.input.value.trim();
        if (q.length < MIN_LENGTH || q === this.loggedQuery) return;
        this.loggedQuery = q;
        fetch(`${this.suggestUrl}?q=${encodeURIComponent(q)}&final=1`, { headers: { Accept: 'application/json' } }).catch(() => {});
    }

    syncClear() {
        if (this.clearBtn) this.clearBtn.hidden = this.input.value === '';
    }

    async update(force = false) {
        const q = this.input.value.trim();
        if (!force && q === this.lastQuery && !this.panel.hidden) return;
        this.lastQuery = q;

        if (q.length < MIN_LENGTH) {
            const popular = await this.popular();
            this.renderEmpty(popular);
            return;
        }

        this.controller?.abort();
        this.controller = new AbortController();
        this.root.classList.add('is-loading');

        try {
            const res = await fetch(`${this.suggestUrl}?q=${encodeURIComponent(q)}`, {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
            });
            const data = await res.json();
            if (this.input.value.trim() !== q) return;
            this.renderResults(data);
        } catch (e) {
            if (e.name !== 'AbortError') this.close();
        } finally {
            this.root.classList.remove('is-loading');
        }
    }

    async popular() {
        if (popularCache) return popularCache;
        try {
            const res = await fetch(this.suggestUrl, { headers: { Accept: 'application/json' } });
            popularCache = (await res.json()).popular || [];
        } catch {
            popularCache = [];
        }
        return popularCache;
    }

    option(href, className) {
        const a = h('a', `sbox-option ${className || ''}`);
        a.href = href;
        a.setAttribute('role', 'option');
        a.id = `${this.uid}-opt-${this.options.length}`;
        a.tabIndex = -1;
        this.options.push(a);
        return a;
    }

    group(title, action) {
        const g = h('div', 'sbox-group');
        g.setAttribute('role', 'group');
        const head = h('div', 'sbox-group-title');
        head.append(h('span', null, title));
        if (action) head.append(action);
        g.setAttribute('aria-label', title);
        g.append(head);
        return g;
    }

    reset() {
        this.panel.replaceChildren();
        this.options = [];
        this.active = -1;
        this.input.removeAttribute('aria-activedescendant');
    }

    renderEmpty(popular) {
        this.reset();
        const items = recent.get();

        if (items.length) {
            const clear = h('button', 'sbox-link', t('recent_clear'));
            clear.type = 'button';
            clear.dataset.recentClear = '';
            const g = this.group(t('group_recent'), clear);
            items.forEach((q) => {
                const a = this.option(`${this.searchUrl}?q=${encodeURIComponent(q)}`, 'sbox-row');
                a.dataset.fill = q;
                a.append(h('span', 'sbox-row-icon'), h('span', 'sbox-row-title', q));
                a.firstChild.append(svg('recent', 16));
                g.append(a);
            });
            this.panel.append(g);
        }

        if (popular.length) {
            const g = this.group(t('group_popular'));
            const wrap = h('div', 'sbox-chips');
            popular.forEach((b) => {
                const a = this.option(b.url, 'sbox-chip');
                a.append(h('span', null, b.title), h('small', null, b.count));
                wrap.append(a);
            });
            g.append(wrap);
            this.panel.append(g);
        }

        const hint = h('div', 'sbox-hint');
        const examples = Array.isArray(config.examples) ? config.examples : [];
        hint.append(`${t('hint')} `);
        examples.forEach((ex, i) => {
            const b = h('button', 'sbox-example', ex);
            b.type = 'button';
            b.dataset.fill = ex;
            hint.append(b);
            if (i < examples.length - 1) hint.append(' ');
        });
        if (examples.length || config.hint) this.panel.append(hint);

        this.open();
    }

    renderResults(data) {
        this.reset();
        const q = data.corrected || data.query;

        if (data.corrected) {
            const n = h('div', 'sbox-notice');
            n.append(`${t('corrected')} `, h('b', null, data.corrected));
            this.panel.append(n);
        } else if (data.relaxed && data.total) {
            this.panel.append(h('div', 'sbox-notice', t('relaxed')));
        }

        if (data.chips?.length) {
            const c = h('div', 'sbox-understood');
            c.append(h('span', null, t('understood')));
            data.chips.forEach((chip) => c.append(h('span', 'chip', chip.label)));
            this.panel.append(c);
        }

        const links = [
            ...(data.brands || []).map((b) => ({ ...b, icon: 'tag' })),
            ...(data.categories || []).map((c) => ({ ...c, icon: 'grid' })),
        ];
        if (links.length) {
            const g = this.group(t('group_links'));
            links.forEach((l) => {
                const a = this.option(l.url, 'sbox-row');
                const icon = h('span', 'sbox-row-icon');
                icon.append(svg(l.icon, 16));
                const title = h('span', 'sbox-row-title');
                title.append(highlight(l.title, q));
                a.append(icon, title, h('span', 'sbox-row-meta', l.subtitle));
                g.append(a);
            });
            this.panel.append(g);
        }

        if (data.cars?.length) {
            const g = this.group(t('group_cars'));
            data.cars.forEach((car) => {
                const a = this.option(car.url, 'sbox-car');
                const media = h('span', 'sbox-car-media');
                if (car.image) {
                    const img = h('img');
                    img.src = car.image;
                    img.alt = '';
                    img.width = 72;
                    img.height = 45;
                    img.loading = 'lazy';
                    img.decoding = 'async';
                    img.addEventListener('error', () => img.replaceWith(svg('car', 22)), { once: true });
                    media.append(img);
                } else {
                    media.append(svg('car', 22));
                }
                const body = h('span', 'sbox-car-body');
                const title = h('span', 'sbox-car-title');
                title.append(highlight(car.title, q));
                body.append(title, h('span', 'sbox-car-meta', car.meta));
                const price = h('span', 'sbox-car-price');
                if (car.price) {
                    price.append(h('small', null, t('price_from')), priceFmt(car.price));
                }
                a.append(media, body, price);
                g.append(a);
            });
            this.panel.append(g);
        }

        if (!data.total) {
            const empty = h('div', 'sbox-empty');
            empty.append(h('b', null, t('empty_title')), h('span', null, t('empty_text')));
            this.panel.append(empty);
        } else {
            const all = this.option(data.url, 'sbox-all');
            all.append(h('span', null, `${t('all_results')} · ${data.total}`), svg('arrow', 16));
            this.panel.append(all);
        }

        this.open();
    }

    open() {
        this.panel.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        this.root.classList.add('is-open');
    }

    close() {
        this.panel.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.root.classList.remove('is-open');
        this.setActive(-1);
    }

    setActive(i) {
        this.options[this.active]?.classList.remove('is-active');
        this.active = i;
        const el = this.options[i];
        if (el) {
            el.classList.add('is-active');
            el.scrollIntoView({ block: 'nearest' });
            this.input.setAttribute('aria-activedescendant', el.id);
        } else {
            this.input.removeAttribute('aria-activedescendant');
        }
    }

    onKey(e) {
        const n = this.options.length;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (this.panel.hidden) this.update(true);
            else if (n) this.setActive((this.active + 1) % n);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (n) this.setActive(this.active <= 0 ? n - 1 : this.active - 1);
        } else if (e.key === 'Enter') {
            const el = this.options[this.active];
            if (el) {
                e.preventDefault();
                if (el.dataset.fill) {
                    this.input.value = el.dataset.fill;
                    this.syncClear();
                    this.update(true);
                    return;
                }
                if (this.input.value.trim()) recent.add(this.input.value);
                window.location.href = el.href;
            } else if (!this.form) {
                e.preventDefault();
                const q = this.input.value.trim();
                if (q) {
                    recent.add(q);
                    window.location.href = `${this.searchUrl}?q=${encodeURIComponent(q)}`;
                }
            }
        } else if (e.key === 'Escape') {
            if (!this.panel.hidden) {
                e.preventDefault();
                e.stopPropagation();
                this.close();
            }
        }
    }
}

function initPalette() {
    const dialog = document.getElementById('search-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const input = dialog.querySelector('.sbox-input');

    const open = () => {
        if (!dialog.open) dialog.showModal();
        requestAnimationFrame(() => { input.focus(); input.select(); });
    };

    document.querySelectorAll('[data-search-open]').forEach((btn) => btn.addEventListener('click', open));
    // command="show-modal" на кнопке уже мог открыть диалог — просто ставим фокус
    dialog.addEventListener('toggle', () => { if (dialog.open) input.focus(); });

    dialog.addEventListener('click', (e) => {
        if (e.target === dialog || e.target.closest('[data-search-close]')) dialog.close();
    });

    document.addEventListener('keydown', (e) => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
        if ((e.key === 'k' || e.key === 'K' || e.code === 'KeyK') && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            dialog.open ? dialog.close() : open();
        } else if (e.key === '/' && !typing && !dialog.open) {
            e.preventDefault();
            open();
        }
    });

    document.querySelectorAll('[data-shortcut-label]').forEach((el) => { el.textContent = isMac ? '⌘K' : 'Ctrl K'; });
}

export function initSearch() {
    document.querySelectorAll('[data-search-box]').forEach((root) => new SearchBox(root));
    initPalette();
}
