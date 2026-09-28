/**
 * «Подмигнуть» кнопкой: один мягкий блик и лёгкий сдвиг стрелки — только в ответ на действие посетителя.
 * При «уменьшить движение» в системе анимацию гасит общее правило в app.css.
 */
export function nudge(el) {
    if (!el || el.hidden || el.disabled) return;
    el.classList.remove('is-nudge');
    void el.offsetWidth;
    el.classList.add('is-nudge');
    clearTimeout(el.nudgeTimer);
    el.nudgeTimer = setTimeout(() => el.classList.remove('is-nudge'), 1300);
}
