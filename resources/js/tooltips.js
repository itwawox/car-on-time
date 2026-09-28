/**
 * Подсказки: у кнопок-иконок («В избранное», «Сравнить», тема, мессенджеры, галерея), у значков карточек
 * («почему „Выгодно“»), у кнопок «Очистить …» и у обрезанных названий машин (data-tooltip-overflow).
 *
 * Текст — из data-tooltip, aria-label (у иконок он и так есть для экранных читалок) или сам обрезанный текст.
 * Показываем при наведении мышью (с задержкой) и при переходе клавишей Tab; на телефоне не мешаем.
 * У кнопок с видимой подписью подсказки нет — она бы только дублировала текст.
 */
const SELECTOR = '[data-tooltip], [data-tooltip-overflow], button[aria-label], a[aria-label]';
const DELAY = 300;

function textOf(el) {
    if (el.hasAttribute('data-tooltip-overflow')) return el.textContent.trim();

    return el.dataset.tooltip || el.getAttribute('aria-label') || el.dataset.nativeTitle || '';
}

/** Текст обрезан многоточием или по числу строк — показываем его целиком. */
const truncated = (el) => el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1;

function eligible(el) {
    if (el.hasAttribute('data-no-tooltip')) return false;
    if (el.hasAttribute('data-tooltip-overflow')) return truncated(el);
    if (el.hasAttribute('data-tooltip')) return true;

    return el.textContent.trim() === '' && textOf(el) !== '';
}

/** Системная подсказка из title появилась бы вместе с нашей — забираем её текст себе. */
function takeNativeTitle(el) {
    if (!el.title) return;
    el.dataset.nativeTitle = el.title;
    el.removeAttribute('title');
}

export function initTooltips() {
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)');
    let tip = null;
    let target = null;
    let timer = null;

    function place(el) {
        const r = el.getBoundingClientRect();
        const { width, height } = tip.getBoundingClientRect();
        const left = Math.min(Math.max(8, r.left + r.width / 2 - width / 2), window.innerWidth - width - 8);
        const above = r.top - height - 8;
        tip.style.left = `${left}px`;
        tip.style.top = `${above >= 8 ? above : r.bottom + 8}px`;
    }

    function show(el) {
        const text = textOf(el);
        if (!text || !el.isConnected) return;
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.setAttribute('role', 'tooltip');
            tip.setAttribute('aria-hidden', 'true');
            document.body.append(tip);
        }
        tip.textContent = text;
        place(el);
        tip.classList.add('is-visible');
    }

    function hide() {
        clearTimeout(timer);
        target = null;
        tip?.classList.remove('is-visible');
    }

    document.addEventListener('pointerover', (e) => {
        if (!canHover.matches || e.pointerType !== 'mouse') return;
        const el = e.target.closest(SELECTOR);
        if (!el || el === target || !eligible(el)) return;
        takeNativeTitle(el);
        hide();
        target = el;
        timer = setTimeout(() => show(el), DELAY);
    });
    document.addEventListener('pointerout', (e) => {
        if (target && !target.contains(e.relatedTarget)) hide();
    });

    document.addEventListener('focusin', (e) => {
        const el = e.target.closest(SELECTOR);
        if (!el || !eligible(el) || !el.matches(':focus-visible')) return;
        takeNativeTitle(el);
        target = el;
        show(el);
    });
    document.addEventListener('focusout', hide);

    // Нажали, прокрутили или нажали Escape — подсказка больше не нужна
    document.addEventListener('pointerdown', hide);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hide(); });
    window.addEventListener('scroll', hide, { passive: true });
}
