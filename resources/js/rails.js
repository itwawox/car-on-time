/**
 * Горизонтальные ленты ([data-rail]): «Вы смотрели», полки машин, миниатюры, фильтры, таблица сравнения.
 * — пальцем листаются нативно (картинки и ссылки не «хватаются» при свайпе);
 * — мышью можно тянуть ленту, клик после перетаскивания не срабатывает;
 * — на устройствах с мышью — стрелки «назад/вперёд», края плавно затемняются, полосы прокрутки нет;
 * — содержимое, которое дорисовал JS (история просмотров), подхватывается автоматически.
 */
const ARROW = (d) => `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${d}"/></svg>`;

function setup(rail) {
    if (rail.dataset.railReady) return;
    rail.dataset.railReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'rail-wrap';
    rail.before(wrap);
    wrap.append(rail);

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.className = 'rail-btn rail-prev';
    prev.setAttribute('aria-label', 'Прокрутить назад');
    prev.innerHTML = ARROW('m15 6-6 6 6 6');
    const next = document.createElement('button');
    next.type = 'button';
    next.className = 'rail-btn rail-next';
    next.setAttribute('aria-label', 'Прокрутить вперёд');
    next.innerHTML = ARROW('m9 6 6 6-6 6');
    wrap.append(prev, next);

    const update = () => {
        const max = rail.scrollWidth - rail.clientWidth;
        const scrollable = max > 4;
        wrap.classList.toggle('is-scrollable', scrollable);
        wrap.classList.toggle('at-start', rail.scrollLeft <= 4);
        wrap.classList.toggle('at-end', rail.scrollLeft >= max - 4);
    };
    const step = (dir) => {
        const first = rail.firstElementChild;
        const unit = first ? first.getBoundingClientRect().width + parseFloat(getComputedStyle(rail).columnGap || getComputedStyle(rail).gap || 0) : rail.clientWidth * 0.8;
        const perPage = Math.max(1, Math.floor(rail.clientWidth / unit));
        rail.scrollBy({ left: dir * unit * perPage, behavior: 'smooth' });
    };
    prev.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    rail.addEventListener('scroll', update, { passive: true });
    new ResizeObserver(update).observe(rail);
    new MutationObserver(update).observe(rail, { childList: true, subtree: true });
    update();

    // Картинки и ссылки не перетаскиваются как файлы — иначе свайп «хватает» их вместо прокрутки
    rail.addEventListener('dragstart', (e) => e.preventDefault());

    // Перетаскивание мышью
    let start = null;
    let dragged = false;
    rail.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0 || !wrap.classList.contains('is-scrollable')) return;
        start = { x: e.clientX, left: rail.scrollLeft };
        dragged = false;
    });
    window.addEventListener('pointermove', (e) => {
        if (!start) return;
        const dx = e.clientX - start.x;
        if (!dragged && Math.abs(dx) > 6) {
            dragged = true;
            rail.classList.add('is-dragging');
        }
        if (dragged) rail.scrollLeft = start.left - dx;
    });
    window.addEventListener('pointerup', () => {
        if (!start) return;
        start = null;
        if (dragged) {
            rail.classList.remove('is-dragging');
            // Клик, завершивший перетаскивание, не должен открывать карточку
            rail.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); }, { capture: true, once: true });
            setTimeout(() => { dragged = false; }, 0);
        }
    });

    // Клавиатура: фокус на элементе внутри ленты — прокручиваем к нему
    rail.addEventListener('focusin', (e) => {
        const item = e.target.closest('[data-rail] > *');
        item?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    });
}

export function initRails(root = document) {
    root.querySelectorAll('[data-rail]').forEach(setup);
}
