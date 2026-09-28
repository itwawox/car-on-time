import { nudge } from './nudge';

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
    const submitCount = form.querySelector('[data-qz-submit-count]');
    const need = form.querySelector('[data-qz-need]');
    let current = 0;
    let lastCount = null;
    let nudgePending = false;
    let dir = 1;

    const answered = (step) => step.querySelector('input:checked') !== null;
    const firstUnanswered = () => steps.findIndex((s) => s.dataset.multiple !== '1' && !answered(s));

    // Нажали «Показать» без ответа: ведём к вопросу, варианты качнутся, под ними — что нужно сделать
    function askFor(index) {
        show(index);
        const step = steps[index];
        const title = step.querySelector('.qz-title')?.lastChild?.textContent.trim() ?? '';
        if (need) {
            need.textContent = (form.dataset.needText || 'Выберите ответ на вопрос «{question}»').replace('{question}', title);
            need.hidden = false;
        }
        const options = step.querySelector('.qz-options');
        options?.classList.remove('is-attention');
        void options?.offsetWidth;
        options?.classList.add('is-attention');
        step.querySelector('input')?.focus({ preventScroll: true });
    }
    function clearNeed() {
        if (need) need.hidden = true;
        form.querySelectorAll('.qz-options.is-attention').forEach((el) => el.classList.remove('is-attention'));
    }
    // Раньше общего обработчика форм (защита от двойной отправки), иначе кнопка «зависнет»
    form.addEventListener('submit', (e) => {
        const missing = firstUnanswered();
        if (missing === -1) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        askFor(missing);
    }, true);

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
        // Кнопку не отключаем: по нажатию квиз сам покажет, на какой вопрос осталось ответить
        submit.setAttribute('aria-disabled', String(firstUnanswered() !== -1));
        next.textContent = step.dataset.multiple === '1' && !answered(step) ? 'Пропустить →' : 'Далее →';
        labels(false);
        step.querySelector('input')?.focus({ preventScroll: true });
    }

    // Кнопки говорят, что будет дальше: «Далее — подходит 12 машин», «Показать мои варианты · 8 машин».
    // Подмигивают только после выбора посетителя, когда пора нажать.
    const cars = (n) => `${n} ${plural(n, ['машина', 'машины', 'машин'])}`;
    function labels(allowNudge = true) {
        const step = steps[current];
        const last = current === steps.length - 1;
        if (!last && step.dataset.multiple === '1' && answered(step)) {
            next.textContent = lastCount ? `Далее — подходит ${cars(lastCount)} →` : 'Далее →';
        }
        if (submitCount) submitCount.textContent = last && answered(step) && lastCount ? ` · ${cars(lastCount)}` : '';
        if (nudgePending && allowNudge) {
            nudgePending = false;
            nudge(last ? submit : next);
        }
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
        clearNeed();
        const step = e.target.closest('[data-qz-step]');
        count();
        if (!step) return;
        const card = e.target.closest('.qz-option')?.querySelector('.qz-card');
        card?.classList.remove('is-pop');
        void card?.offsetWidth;
        card?.classList.add('is-pop');
        if (step.dataset.multiple === '1') { nudgePending = true; show(current); return; }
        const i = steps.indexOf(step);
        if (i < steps.length - 1) setTimeout(() => show(i + 1), 280);
        else { nudgePending = true; show(i); }
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
                lastCount = n || null;
                countEl.textContent = n ? `Подходит ${n} ${plural(n, ['машина', 'машины', 'машин'])}` : 'Точных совпадений нет — покажем ближайшие';
                countEl.classList.remove('is-bump');
                void countEl.offsetWidth;
                countEl.classList.add('is-bump');
            } catch { /* счётчик необязателен */ }
            labels();
        }, 150);
    }

    // Если пришли по «Изменить ответы» — начинаем с первого неотвеченного
    const firstEmpty = firstUnanswered();
    show(firstEmpty === -1 ? steps.length - 1 : firstEmpty);
    if (steps.some(answered)) count();
}
