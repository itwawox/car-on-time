/**
 * Аналитика: цели Яндекс Метрики, электронная коммерция (dataLayer) и источник перехода.
 * Метрика грузится только после согласия на cookie (см. layouts/app) — до этого цели не отправляются.
 * Источник (UTM, yclid, первый переход) хранится в браузере 30 дней и уходит с заявкой в скрытом поле utm.
 */
const ATTR_KEY = 'attr:first';
const ATTR_TTL = 30 * 24 * 3600 * 1000;
const ATTR_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'gclid'];

const store = {
    get(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } },
    set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* приватный режим */ } },
};

window.dataLayer = window.dataLayer || [];

/** Цель Метрики. До загрузки счётчика складываем в очередь — её разберёт загрузчик. */
export function goal(name, params = {}) {
    const id = window.ymCounterId;
    if (id && typeof window.ym === 'function') {
        window.ym(id, 'reachGoal', name, params);
    } else {
        (window.ymPendingGoals = window.ymPendingGoals || []).push([name, params]);
    }
}

function once(key, fn) {
    const k = `goal:${key}`;
    try {
        if (sessionStorage.getItem(k)) return;
        sessionStorage.setItem(k, '1');
    } catch { /* без sessionStorage — просто отправим */ }
    fn();
}

function rememberSource() {
    const url = new URL(window.location.href);
    const fromUrl = Object.fromEntries(ATTR_PARAMS.filter((p) => url.searchParams.get(p)).map((p) => [p, url.searchParams.get(p).slice(0, 255)]));
    const saved = store.get(ATTR_KEY);
    const expired = !saved || Date.now() - (saved.ts || 0) > ATTR_TTL;
    const external = document.referrer && new URL(document.referrer).host !== window.location.host;

    // Новая рекламная метка перебивает старую; обычный заход — только если источник ещё не запомнен
    if (Object.keys(fromUrl).length || expired) {
        store.set(ATTR_KEY, {
            ...fromUrl,
            ...(external ? { referrer: document.referrer.slice(0, 255) } : {}),
            landing: (url.pathname + url.search).slice(0, 255),
            ts: Date.now(),
        });
    }
}

function attachSource(form) {
    const data = store.get(ATTR_KEY);
    if (!data) return;
    let input = form.querySelector('input[name="utm"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'utm';
        form.append(input);
    }
    const { ts, ...rest } = data;
    input.value = JSON.stringify(rest);
}

function readJson(el, attr) {
    try { return JSON.parse(el.getAttribute(attr) || 'null'); } catch { return null; }
}

export function initAnalytics() {
    rememberSource();

    // Отправка форм: источник в заявку + цель. Фаза захвата — раньше, чем формы отправятся через fetch
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const action = form.getAttribute('action') || '';
        if (action.includes('/poisk')) goal('search');
        if (form.method.toLowerCase() !== 'post') return;
        if (action.includes('/zayavka')) {
            attachSource(form);
            goal('booking_submit');
        } else if (action.includes('/obratnyj-zvonok')) {
            attachSource(form);
            goal('lead_submit', { type: form.querySelector('[name="type"]')?.value || 'callback' });
        }
    }, true);

    // Звонки и мессенджеры
    document.addEventListener('click', (e) => {
        const link = e.target.closest?.('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (href.startsWith('tel:')) goal('phone_click');
        else if (/wa\.me|whatsapp/i.test(href)) goal('messenger_click', { messenger: 'whatsapp' });
        else if (/t\.me|telegram/i.test(href)) goal('messenger_click', { messenger: 'telegram' });
        else if (/max\.ru/i.test(href)) goal('messenger_click', { messenger: 'max' });
    });

    // Карточка машины: просмотр товара, начало заполнения, выбор дат, промокод
    const carForm = document.querySelector('[data-analytics-car]');
    if (carForm) {
        const car = readJson(carForm, 'data-analytics-car');
        if (car) {
            goal('car_view', { car: car.name });
            window.dataLayer.push({ ecommerce: { currencyCode: 'RUB', detail: { products: [car] } } });
        }
        carForm.addEventListener('focusin', () => once(`form_start:${car?.id}`, () => goal('booking_form_start')), { once: true });
        carForm.addEventListener('change', (e) => {
            if (e.target.name === 'ends_at' || e.target.name === 'starts_at') once(`dates:${car?.id}`, () => goal('dates_selected'));
        });
        carForm.addEventListener('quote:updated', (e) => {
            if (e.detail?.promo?.ok) once(`promo:${e.detail.promo.code}`, () => goal('promo_applied', { code: e.detail.promo.code }));
        });
    }

    // Страница «Спасибо»: заявка отправлена + покупка для электронной коммерции (один раз на заявку)
    const purchase = document.querySelector('[data-analytics-purchase]');
    if (purchase) {
        const order = readJson(purchase, 'data-analytics-purchase');
        const key = `purchase:${order?.id}`;
        if (order && !store.get(key)) {
            store.set(key, 1);
            goal('booking_sent', { order_price: order.revenue, currency: 'RUB' });
            window.dataLayer.push({
                ecommerce: {
                    currencyCode: 'RUB',
                    purchase: { actionField: { id: String(order.id), revenue: order.revenue }, products: [order.product] },
                },
            });
        }
    }

    // Прочие цели страниц: <div data-analytics-goal="quiz_result">
    document.querySelectorAll('[data-analytics-goal]').forEach((el) => goal(el.dataset.analyticsGoal));
}
