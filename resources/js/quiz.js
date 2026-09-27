/**
 * Квиз подбора: по одному вопросу на экран, прогресс, автопереход после выбора,
 * «Назад/Далее», живой счётчик подходящих машин. Без JS — обычная форма со всеми вопросами.
 */
const plural = (n, forms) => {
    const a = Math.abs(n) % 100;
    const b = a % 10;
    if (a > 10 && a < 20) return forms[2];
    if (b > 1 && b < 5) return forms[1];
    return b === 1 ? forms[0] : forms[2];
};

export function initQuiz() {
    const form = document.querySelector('[data-quiz]');
    if (!form) return;
    form.classList.add('is-stepped');

    const steps = [...form.querySelectorAll('[data-qz-step]')];
    const bar = form.querySelector('[data-qz-bar]');
    const counter = form.querySelector('[data-qz-counter]');
    const countEl = form.querySelector('[data-qz-count]');
    const back = form.querySelector('[data-qz-back]');
    const next = form.querySelector('[data-qz-next]');
    const submit = form.querySelector('[data-qz-submit]');
    let current = 0;
    let dir = 1;

    const answered = (step) => step.querySelector('input:checked') !== null;

    function show(index) {
        dir = index >= current ? 1 : -1;
        current = Math.max(0, Math.min(steps.length - 1, index));
        steps.forEach((s, i) => {
            s.classList.toggle('is-active', i === current);
            s.classList.toggle('from-left', i === current && dir < 0);
        });
        const step = steps[current];
        const last = current === steps.length - 1;
        bar.style.width = `${((current + (answered(step) ? 1 : 0)) / steps.length) * 100}%`;
        counter.textContent = `Шаг ${current + 1} из ${steps.length}`;
        back.hidden = current === 0;
        // «Далее» — на шаге с несколькими ответами и на уже отвеченном шаге (вернулись «Назад»)
        next.hidden = last || (step.dataset.multiple !== '1' && !answered(step));
        submit.hidden = !last;
        submit.disabled = !steps.every((s) => s.dataset.multiple === '1' || answered(s));
        next.textContent = step.dataset.multiple === '1' && !answered(step) ? 'Пропустить →' : 'Далее →';
        step.querySelector('input')?.focus({ preventScroll: true });
    }

    // Повторное нажатие на уже выбранный вариант: браузер не присылает change — переходим дальше сами
    let changed = false;
    form.addEventListener('click', (e) => {
        const input = e.target.closest('input[type="radio"]');
        if (!input) return;
        changed = false;
        setTimeout(() => {
            if (changed || !input.checked) return;
            const i = steps.indexOf(input.closest('[data-qz-step]'));
            if (i === current && i < steps.length - 1) show(i + 1);
        }, 0);
    });

    // Автопереход после выбора одного варианта
    form.addEventListener('change', (e) => {
        changed = true;
        const step = e.target.closest('[data-qz-step]');
        count();
        if (!step) return;
        const card = e.target.closest('.qz-option')?.querySelector('.qz-card');
        card?.classList.remove('is-pop');
        void card?.offsetWidth;
        card?.classList.add('is-pop');
        if (step.dataset.multiple === '1') { show(current); return; }
        const i = steps.indexOf(step);
        if (i < steps.length - 1) setTimeout(() => show(i + 1), 280);
        else show(i);
    });
    back.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && current < steps.length - 1) { e.preventDefault(); if (answered(steps[current]) || steps[current].dataset.multiple === '1') show(current + 1); }
    });

    // Живой счётчик
    let timer;
    let controller;
    function count() {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const res = await fetch(`${form.dataset.countUrl}?${new URLSearchParams(new FormData(form))}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                const { count: n } = await res.json();
                countEl.textContent = n ? `Подходит ${n} ${plural(n, ['машина', 'машины', 'машин'])}` : 'Точных совпадений нет — покажем ближайшие';
                countEl.classList.remove('is-bump');
                void countEl.offsetWidth;
                countEl.classList.add('is-bump');
            } catch { /* счётчик необязателен */ }
        }, 150);
    }

    // Если пришли по «Изменить ответы» — начинаем с первого неотвеченного
    const firstEmpty = steps.findIndex((s) => s.dataset.multiple !== '1' && !answered(s));
    show(firstEmpty === -1 ? steps.length - 1 : firstEmpty);
    if (steps.some(answered)) count();
}
