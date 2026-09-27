/**
 * Галерея на странице машины: миниатюры + просмотр на весь экран с увеличением.
 * Увеличение: клик/двойной тап, колесо мыши, щипок двумя пальцами; перемещение — перетаскиванием.
 * Листание: стрелки, свайп, клавиши ← →. Закрытие: Esc, крестик, клик по фону.
 */
const MAX_SCALE = 4;

export function initGallery() {
    const root = document.querySelector('[data-gallery]');
    if (!root) return;

    let items = [];
    try { items = JSON.parse(root.querySelector('[data-gallery-items]')?.textContent || '[]'); } catch { items = []; }
    if (!items.length) return;

    const main = root.querySelector('[data-gallery-main]');
    const thumbs = [...root.querySelectorAll('[data-gallery-thumb]')];
    let current = 0;

    thumbs.forEach((thumb) => thumb.addEventListener('click', () => {
        current = Number(thumb.dataset.galleryThumb);
        const img = thumb.querySelector('img');
        main.removeAttribute('srcset');
        main.src = items[current].src;
        main.alt = items[current].alt;
        thumbs.forEach((t) => t.classList.toggle('is-active', t === thumb));
        root.querySelector('[data-gallery-open]').dataset.galleryOpen = String(current);
        img?.blur();
    }));

    root.querySelector('[data-gallery-open]')?.addEventListener('click', (e) => {
        open(Number(e.currentTarget.dataset.galleryOpen || 0));
    });

    let dialog;
    let state = { scale: 1, x: 0, y: 0 };

    function build() {
        dialog = document.createElement('dialog');
        dialog.className = 'lightbox';
        dialog.setAttribute('aria-label', 'Фото автомобиля');
        dialog.innerHTML = `
            <div class="lightbox-stage" data-stage><img class="lightbox-img" alt="" draggable="false" data-img></div>
            <div class="lightbox-bar">
                <span class="lightbox-count" data-count></span>
                <button type="button" class="lightbox-btn" data-zoom-out aria-label="Уменьшить">${icon('M8 11h6', true)}</button>
                <button type="button" class="lightbox-btn" data-zoom-in aria-label="Увеличить">${icon('M11 8v6M8 11h6', true)}</button>
                <button type="button" class="lightbox-btn" data-close aria-label="Закрыть">${icon('M6 6l12 12M18 6 6 18')}</button>
            </div>
            ${items.length > 1 ? `
                <button type="button" class="lightbox-nav lightbox-prev" data-prev aria-label="Предыдущее фото">${icon('m15 6-6 6 6 6')}</button>
                <button type="button" class="lightbox-nav lightbox-next" data-next aria-label="Следующее фото">${icon('m9 6 6 6-6 6')}</button>` : ''}
        `;
        document.body.append(dialog);

        const stage = dialog.querySelector('[data-stage]');
        const img = dialog.querySelector('[data-img]');

        dialog.querySelector('[data-close]').addEventListener('click', close);
        dialog.querySelector('[data-zoom-in]').addEventListener('click', () => zoomTo(state.scale * 1.6));
        dialog.querySelector('[data-zoom-out]').addEventListener('click', () => zoomTo(state.scale / 1.6));
        dialog.querySelector('[data-prev]')?.addEventListener('click', () => show(current - 1));
        dialog.querySelector('[data-next]')?.addEventListener('click', () => show(current + 1));
        dialog.addEventListener('close', () => document.documentElement.classList.remove('lightbox-open'));
        dialog.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') show(current - 1);
            if (e.key === 'ArrowRight') show(current + 1);
            if (e.key === '+' || e.key === '=') zoomTo(state.scale * 1.6);
            if (e.key === '-') zoomTo(state.scale / 1.6);
        });

        stage.addEventListener('wheel', (e) => {
            e.preventDefault();
            zoomTo(state.scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX, e.clientY);
        }, { passive: false });

        // Указатели: перетаскивание, щипок, свайп, тап/клик
        const pointers = new Map();
        let start = null;
        let pinch = null;
        let moved = false;

        stage.addEventListener('pointerdown', (e) => {
            stage.setPointerCapture(e.pointerId);
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            moved = false;
            if (pointers.size === 1) start = { x: e.clientX, y: e.clientY, sx: state.x, sy: state.y, t: Date.now() };
            if (pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), scale: state.scale };
            }
        });
        stage.addEventListener('pointermove', (e) => {
            if (!pointers.has(e.pointerId)) return;
            pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (pinch && pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                zoomTo(pinch.scale * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d, (a.x + b.x) / 2, (a.y + b.y) / 2);
                moved = true;
                return;
            }
            if (!start) return;
            const dx = e.clientX - start.x;
            const dy = e.clientY - start.y;
            if (Math.abs(dx) + Math.abs(dy) > 6) moved = true;
            if (state.scale > 1) {
                state.x = start.sx + dx;
                state.y = start.sy + dy;
                apply();
            }
        });
        const end = (e) => {
            if (!pointers.has(e.pointerId)) return;
            pointers.delete(e.pointerId);
            if (pointers.size < 2) pinch = null;
            if (pointers.size > 0 || !start) return;

            const dx = e.clientX - start.x;
            if (state.scale === 1 && moved && Math.abs(dx) > 60 && Date.now() - start.t < 600) {
                show(current + (dx < 0 ? 1 : -1));
            } else if (!moved && e.type === 'pointerup') {
                // Клик по фону вокруг фото закрывает, по фото — увеличивает / возвращает
                const r = img.getBoundingClientRect();
                const onImage = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
                if (!onImage && state.scale === 1) close();
                else if (state.scale > 1) zoomTo(1);
                else zoomTo(2.5, e.clientX, e.clientY);
            }
            start = null;
            clamp();
            apply();
        };
        stage.addEventListener('pointerup', end);
        stage.addEventListener('pointercancel', end);

        function zoomTo(scale, cx, cy) {
            const next = Math.min(MAX_SCALE, Math.max(1, scale));
            const rect = stage.getBoundingClientRect();
            const px = (cx ?? rect.left + rect.width / 2) - rect.left - rect.width / 2;
            const py = (cy ?? rect.top + rect.height / 2) - rect.top - rect.height / 2;
            // Точка под курсором остаётся на месте
            state.x = px - (px - state.x) * (next / state.scale);
            state.y = py - (py - state.y) * (next / state.scale);
            state.scale = next;
            if (next === 1) state = { scale: 1, x: 0, y: 0 };
            clamp();
            apply();
        }

        function clamp() {
            const rect = stage.getBoundingClientRect();
            const w = img.offsetWidth * state.scale;
            const h = img.offsetHeight * state.scale;
            const maxX = Math.max(0, (w - rect.width) / 2);
            const maxY = Math.max(0, (h - rect.height) / 2);
            state.x = Math.min(maxX, Math.max(-maxX, state.x));
            state.y = Math.min(maxY, Math.max(-maxY, state.y));
        }

        function apply() {
            img.style.transform = `translate(${state.x}px, ${state.y}px) scale(${state.scale})`;
            dialog.classList.toggle('is-zoomed', state.scale > 1);
            dialog.querySelector('[data-zoom-out]').disabled = state.scale <= 1;
            dialog.querySelector('[data-zoom-in]').disabled = state.scale >= MAX_SCALE;
        }

        dialog.zoomTo = zoomTo;
        dialog.apply = apply;
    }

    function show(index) {
        current = (index + items.length) % items.length;
        const img = dialog.querySelector('[data-img]');
        state = { scale: 1, x: 0, y: 0 };
        img.classList.add('is-loading');
        img.onload = () => img.classList.remove('is-loading');
        img.src = items[current].src;
        img.alt = items[current].alt;
        dialog.querySelector('[data-count]').textContent = items.length > 1 ? `${current + 1} / ${items.length}` : '';
        dialog.apply();
    }

    function open(index) {
        if (!dialog) build();
        dialog.showModal();
        document.documentElement.classList.add('lightbox-open');
        show(index);
    }

    function close() {
        dialog?.close();
    }
}

function icon(d, magnifier = false) {
    const lens = magnifier ? '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>' : '';
    return `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${lens}<path d="${d}"/></svg>`;
}
