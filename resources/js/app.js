import { initSearch } from './search';
import { initCompare } from './compare';
import { initGallery } from './gallery';
import { initCatalog } from './catalog';
import { initMemory } from './memory';
import { initForms } from './forms';
import { initPlacePickers } from './place-picker';
import { initFilters } from './filters';
import { initFavorites } from './favorites';
import { initMaps } from './map';
import { initFaq } from './faq';
import { initQuiz } from './quiz';
import { initRails } from './rails';
import { initHome } from './home';
import { initCookies } from './cookies';
import { initAnalytics } from './analytics';
import { initTooltips } from './tooltips';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function initReveal() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    // Stagger children inside a group
    document.querySelectorAll('[data-reveal-group]').forEach((group) => {
        group.querySelectorAll(':scope > [data-reveal]').forEach((el, i) => {
            el.style.setProperty('--reveal-delay', `${Math.min(i, 8) * 60}ms`);
        });
    });

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    items.forEach((el) => io.observe(el));
}

function initHeader() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    let ticking = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 8);
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(update);
    }, { passive: true });
    update();
}

function initMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    if (!menu || typeof menu.showModal !== 'function') return;

    document.querySelectorAll('[data-menu-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!menu.open) menu.showModal();
            btn.setAttribute('aria-expanded', 'true');
        });
    });

    menu.querySelectorAll('[data-menu-close]').forEach((btn) => btn.addEventListener('click', () => { if (menu.open) menu.close(); }));
    menu.addEventListener('click', (e) => { if (e.target === menu) menu.close(); });
    menu.addEventListener('close', () => {
        document.querySelectorAll('[data-menu-open]').forEach((btn) => btn.setAttribute('aria-expanded', 'false'));
    });
}

function animateNumber(el, to) {
    const from = Number(el.dataset.value || 0);
    el.dataset.value = String(to);
    const format = (n) => Math.round(n).toLocaleString('ru-RU') + ' ₽';

    if (reduceMotion || !from || from === to) {
        el.textContent = format(to);
        return;
    }

    const start = performance.now();
    const duration = 450;
    const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = format(from + (to - from) * eased);
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

// «Чек» расчёта: строки с суммами справа, итог к оплате крупно, залог отдельно
function renderReceipt(el, q) {
    const fmt = (n) => `${Number(n).toLocaleString('ru-RU')} ₽`;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const cost = (n) => (Number(n) > 0 ? fmt(n) : '<span class="qs-free">бесплатно</span>');
    const plural = (n) => (n % 10 === 1 && n % 100 !== 11 ? 'сутки' : 'суток');
    const rows = [];
    (q.lines || []).forEach((l) => rows.push(`<div class="qs-row"><dt>Аренда · ${l.days} ${plural(l.days)} × ${fmt(l.price)}<small>${esc(l.label)}</small></dt><dd>${fmt(l.sum)}</dd></div>`));
    rows.push(`<div class="qs-row"><dt>Выдача${q.pickup_name ? `<small>${esc(q.pickup_name)}</small>` : ''}</dt><dd>${cost(q.pickup_cost)}</dd></div>`);
    rows.push(`<div class="qs-row"><dt>Возврат${q.return_name ? `<small>${esc(q.return_name)}</small>` : ''}</dt><dd>${cost(q.return_cost)}</dd></div>`);
    (q.extras || []).forEach((x) => rows.push(`<div class="qs-row"><dt>${esc(x.name)}</dt><dd>${cost(x.sum)}</dd></div>`));
    if (q.discount > 0) rows.push(`<div class="qs-row qs-discount"><dt>Промокод ${esc(q.promo?.code)}<small>${esc(q.promo?.label)}</small></dt><dd>−${fmt(q.discount)}</dd></div>`);
    el.innerHTML = `<dl class="qs">${rows.join('')}
        <div class="qs-total"><dt>К оплате при получении</dt><dd>${fmt(q.total)}</dd></div>
        ${q.deposit ? `<div class="qs-deposit"><dt>Залог — вернём после сдачи машины</dt><dd>${fmt(q.deposit)}</dd></div>` : ''}
    </dl>`;
}

function initQuote() {
    const form = document.querySelector('[data-quote-form]');
    if (!form) return;

    const summary = document.querySelector('[data-quote-summary]');
    const error = document.querySelector('[data-quote-error]');
    const totals = document.querySelectorAll('[data-quote-total]');
    const daysEl = document.querySelector('[data-quote-days]');
    const perDayEl = document.querySelector('[data-quote-per-day]');
    const lineEl = document.querySelector('[data-quote-line]');
    const availEl = document.querySelector('[data-quote-avail]');
    const minDaysHint = document.querySelector('[data-min-days-hint]');
    const minDays = Number(form.querySelector('[data-daterange]')?.dataset.minDays) || 1;
    const money = (n) => `${Number(n).toLocaleString('ru-RU')} ₽`;
    const daysWord = (n) => (n % 10 === 1 && n % 100 !== 11 ? 'сутки' : 'суток');
    const waLinks = document.querySelectorAll('[data-wa-link]');
    // Кнопка говорит, что будет: «Оставить заявку · 3 суток · 12 400 ₽»
    const submitBtn = form.querySelector('[data-submit]');
    const submitLabel = submitBtn?.textContent.trim() ?? '';
    // После первого расчёта по выбору посетителя подсвечиваем телефон — последний шаг
    const phone = form.querySelector('[name="phone"]');
    const phoneNext = form.querySelector('[data-phone-next]');
    let phoneHinted = false;
    phone?.addEventListener('input', () => {
        phone.classList.remove('is-next');
        if (phoneNext) phoneNext.hidden = true;
    });
    const { quoteUrl, carName, waBase, freeText, busyText } = form.dataset;
    let controller = null;

    const val = (name) => form.querySelector(`[name="${name}"]`)?.value ?? '';

    async function refresh(event) {
        const byVisitor = Boolean(event);
        controller?.abort();
        controller = new AbortController();

        const params = new URLSearchParams({
            car_id: val('car_id'),
            starts_at: val('starts_at'),
            ends_at: val('ends_at'),
            pickup_location_id: val('pickup_location_id'),
            return_location_id: returnPlace(form),
        });
        form.querySelectorAll('[name="extras[]"]:checked').forEach((el) => params.append('extras[]', el.value));
        if (val('promo_code').trim()) params.set('promo_code', val('promo_code').trim());
        // Подсказка про ночной тариф: выдача или возврат с 21:00 до 8:00
        const hour = (v) => Number((v || '').slice(11, 13));
        const night = [val('starts_at'), val('ends_at')].some((v) => v && (hour(v) >= 21 || hour(v) < 8));
        document.querySelector('[data-night-hint]')?.toggleAttribute('hidden', !night);

        summary?.classList.add('skeleton');

        try {
            const res = await fetch(`${quoteUrl}?${params}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const data = await res.json();
            summary?.classList.remove('skeleton');

            minDaysHint?.toggleAttribute('hidden', !(data.days && data.days < minDays));
            if (!data.ok) {
                if (error) error.textContent = data.error || 'Не удалось посчитать';
                if (summary) summary.textContent = 'Проверьте даты — посчитаем аренду и доставку.';
                if (lineEl) lineEl.hidden = true;
                if (availEl) availEl.hidden = true;
                return;
            }

            if (error) error.textContent = data.promo && !data.promo.ok ? data.promo.error : '';
            if (summary) renderReceipt(summary, data);
            totals.forEach((el) => animateNumber(el, data.total));
            if (daysEl) daysEl.textContent = `за ${data.days} ${daysWord(data.days)}`;
            if (perDayEl && data.days) perDayEl.textContent = `${money(Math.round(data.rent_total / data.days))}/сут`;
            if (submitBtn && !form.dataset.sending) {
                submitBtn.textContent = data.days ? `${submitLabel} · ${data.days} ${daysWord(data.days)} · ${money(data.total)}` : submitLabel;
            }
            if (byVisitor && !phoneHinted && phone && phone.value.replace(/\D/g, '').length < 11) {
                phoneHinted = true;
                phone.classList.add('is-next');
                if (phoneNext) phoneNext.hidden = false;
            }
            // Под ценой — только то, что к ней добавляет смысл: доставка уже в сумме, залог отдельно
            if (lineEl) {
                const delivery = (data.pickup_cost || 0) + (data.return_cost || 0);
                lineEl.textContent = [
                    delivery ? `включая доставку ${money(delivery)}` : null,
                    data.deposit ? `залог ${money(data.deposit)} отдельно${form.dataset.depositHint ? ` (${form.dataset.depositHint})` : ''}` : null,
                ].filter(Boolean).join(' · ');
                lineEl.hidden = !lineEl.textContent;
            }
            if (availEl) {
                availEl.hidden = typeof data.available !== 'boolean';
                availEl.className = `qs-avail ${data.available ? 'is-free' : 'is-busy'}`;
                availEl.textContent = data.available ? freeText : busyText;
            }
            form.dispatchEvent(new CustomEvent('quote:updated', { bubbles: true, detail: { total: data.total, days: data.days, promo: data.promo } }));

            if (waBase) {
                const text = `Здравствуйте! Хочу ${carName}, ${val('starts_at')} — ${val('ends_at')}. Итого с сайта ${data.total} ₽. Свободно?`;
                waLinks.forEach((a) => { a.href = `${waBase}?text=${encodeURIComponent(text)}`; });
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            summary?.classList.remove('skeleton');
            if (error) error.textContent = 'Не удалось посчитать. Оставьте заявку — посчитаем вручную.';
        }
    }

    form.querySelectorAll('[data-quote-input]').forEach((el) => el.addEventListener('change', refresh));
    form.querySelector('[data-other-return]')?.addEventListener('change', refresh);
    refresh();

    // «Заявка» в нижней панели: к карточке, курсор — в телефон (даты уже стоят по умолчанию или из памяти)
    document.querySelector('[data-quote-jump]')?.addEventListener('click', (e) => {
        e.preventDefault();
        const card = document.querySelector('.quote-card');
        card?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        setTimeout(() => form.querySelector('[name="phone"]')?.focus({ preventScroll: true }), reduceMotion ? 0 : 450);
    });

    // Hide the mobile sticky bar while the form itself is on screen
    const bar = document.querySelector('.sticky-bar');
    if (bar && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            bar.classList.toggle('is-hidden', entry.isIntersecting);
        }, { threshold: 0.15 }).observe(form);
    }
}

// «Бензин на поездку»: пробег по пресету × расход × цена топлива; плюс к итогу аренды
function initTrip() {
    const box = document.querySelector('[data-trip]');
    if (!box) return;
    const select = box.querySelector('[data-trip-route]');
    const kmInput = box.querySelector('[data-trip-km]');
    const out = box.querySelector('[data-trip-out]');
    const totalEl = box.querySelector('[data-trip-total]');
    const { per100, consumption, unit, grade, price } = box.dataset;
    const fmt = (n) => Math.round(n).toLocaleString('ru-RU');
    let rent = null;
    let days = 3;

    const km = () => {
        if (select.value === 'custom') return Number(kmInput.value) || 0;
        const perDay = select.selectedOptions[0]?.dataset.perDay === '1';
        return Number(select.value) * (perDay ? days : 1);
    };

    function render() {
        kmInput.hidden = select.value !== 'custom';
        const distance = km();
        if (!distance) {
            out.textContent = `≈ ${fmt(per100)} ₽ на 100 км`;
            totalEl.hidden = true;
            return;
        }
        const fuel = (Number(per100) * distance) / 100;
        out.textContent = `≈ ${fmt(fuel)} ₽ за ${fmt(distance)} км · ${consumption} ${unit}/100 км${grade ? `, ${grade}` : ''} по ${price} ₽`;
        if (rent) {
            totalEl.hidden = false;
            totalEl.innerHTML = `Аренда + ${unit === 'л' ? 'бензин' : 'зарядка'} ≈ <b>${fmt(rent + fuel)} ₽</b>`;
        }
    }

    select.addEventListener('change', () => {
        render();
        if (select.value === 'custom') kmInput.focus();
    });
    kmInput.addEventListener('input', render);
    document.addEventListener('quote:updated', (e) => {
        rent = e.detail.total;
        days = e.detail.days || days;
        render();
    });
    render();
}

// Место выдачи/возврата: запоминаем выбор на главной или из ссылки каталога, подставляем в заявку
const PLACE_KEY = 'trip:place';
function initPlaceMemory() {
    const params = new URLSearchParams(location.search);
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem(PLACE_KEY) || 'null'); } catch { saved = null; }
    if (params.get('from')) {
        saved = { from: params.get('from'), to: params.get('to') || params.get('from') };
        try { localStorage.setItem(PLACE_KEY, JSON.stringify(saved)); } catch { /* приватный режим */ }
    }
    if (!saved) return;
    document.querySelectorAll('select[data-remember-place]').forEach((select) => {
        const value = saved[select.dataset.rememberPlace];
        if (value && select.querySelector(`option[value="${CSS.escape(String(value))}"]`)) select.value = value;
    });
}

// Hero: вкладки «По датам / По названию» и «Вернуть в другом месте»
function initHero() {
    const tabs = document.querySelector('[data-hero-tabs]');
    if (!tabs) return;
    const buttons = [...tabs.querySelectorAll('[role="tab"]')];
    const select = (btn) => {
        buttons.forEach((b) => {
            const on = b === btn;
            b.setAttribute('aria-selected', String(on));
            b.tabIndex = on ? 0 : -1;
            const panel = document.getElementById(b.getAttribute('aria-controls'));
            panel?.toggleAttribute('data-hero-panel-inactive', !on);
        });
    };
    buttons.forEach((b, i) => {
        b.addEventListener('click', () => select(b));
        b.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const next = buttons[(i + (e.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length];
            select(next);
            next.focus();
        });
    });

    const form = document.querySelector('[data-hero-book]');
    const other = form?.querySelector('[data-other-return]');
    form?.addEventListener('submit', () => {
        const from = form.querySelector('[name="from"]').value;
        const to = other?.checked ? form.querySelector('[name="to"]').value : from;
        try { localStorage.setItem(PLACE_KEY, JSON.stringify({ from, to })); } catch { /* приватный режим */ }
    });
}

/** Место возврата: второе поле открыто — его значение, иначе то же место, что выдача. */
function returnPlace(form) {
    const select = form.querySelector('[name="return_location_id"], [name="to"]');
    return select && !select.disabled ? select.value : (form.querySelector('[name="pickup_location_id"], [name="from"]')?.value ?? '');
}

// «Вернуть в другом месте»: флажок открывает второе поле; по умолчанию возврат — там же, где выдача
function initOtherReturn() {
    document.querySelectorAll('[data-other-return]').forEach((box) => {
        const scope = box.closest('form, [data-other-return-scope]');
        const wrap = scope?.querySelector('[data-return-place]');
        const select = wrap?.querySelector('select');
        if (!wrap || !select) return;
        const pickup = scope.querySelector('[data-pickup], [name="from"]');
        const apply = () => {
            wrap.hidden = !box.checked;
            select.disabled = !box.checked;
            if (!box.checked && pickup) select.value = pickup.value;
        };
        // Место возврата из памяти отличается от выдачи — сразу показываем второе поле
        if (pickup && select.value && select.value !== pickup.value) box.checked = true;
        box.addEventListener('change', apply);
        pickup?.addEventListener('change', () => { if (!box.checked) select.value = pickup.value; });
        apply();
    });
}

// «← Назад к результатам»: пришли в карточку из каталога/подборки — возвращаем к той же выдаче с фильтрами и прокруткой
function initBackToResults() {
    const link = document.querySelector('[data-back-results]');
    if (!link || !document.referrer) return;
    let ref;
    try { ref = new URL(document.referrer); } catch { return; }
    if (ref.origin !== location.origin) return;
    if (!/^\/(katalog|klass|kuzov|korobka|marka|arenda-avto-v-|poisk|izbrannoe|sravnenie|podbor\/rezultat|$)/.test(ref.pathname)) return;
    link.href = ref.pathname + ref.search;
    if (ref.pathname === '/') link.lastChild.textContent = ' На главную';
    link.hidden = false;
    link.addEventListener('click', (e) => {
        if (history.length > 1) { e.preventDefault(); history.back(); }
    });
}

// Плавающая кнопка «Написать»: закрывается по клику вне меню и по Esc
function initFab() {
    const fab = document.querySelector('[data-fab]');
    if (!fab) return;
    document.addEventListener('click', (e) => { if (fab.open && !fab.contains(e.target)) fab.open = false; });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && fab.open) fab.open = false; });
}

// Скелетоны картинок: мерцающий фон, пока фото грузится
function initImageSkeletons() {
    document.querySelectorAll('[data-skeleton] img').forEach((img) => {
        const done = () => img.closest('[data-skeleton]')?.classList.add('is-loaded');
        if (img.complete && img.naturalWidth) done();
        else {
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
        }
    });
}

// Календарь периода аренды грузится отдельным файлом только на странице машины
function initDateRangeLazy() {
    const root = document.querySelector('[data-daterange]');
    if (root) import('./daterange').then(({ initDateRange }) => initDateRange(root));
}

// Тема: по умолчанию системная, кнопка в шапке переключает и запоминает выбор
function initTheme() {
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const isDark = () => root.dataset.theme ? root.dataset.theme === 'dark' : media.matches;
    const sync = () => {
        const dark = isDark();
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const label = dark ? 'Светлая тема' : 'Тёмная тема';
            btn.setAttribute('aria-label', label);
            btn.title = label;
        });
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', dark ? '#0d161c' : '#0f3344');
    };
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => btn.addEventListener('click', () => {
        const next = isDark() ? 'light' : 'dark';
        root.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch { /* приватный режим */ }
        sync();
    }));
    media.addEventListener?.('change', sync);
    sync();
}

initTheme();
initAnalytics();
initPlaceMemory();
initPlacePickers();
initHero();
initOtherReturn();
initImageSkeletons();
initFab();
initReveal();
initHeader();
initMobileMenu();
initDateRangeLazy();
initTrip();
initBackToResults();
initQuote();
initSearch();
initCompare();
initGallery();
initCatalog();
initMemory();
initForms();
initFilters();
initFavorites();
initMaps();
initFaq();
initQuiz();
initRails();
initHome();
initCookies();
initTooltips();
