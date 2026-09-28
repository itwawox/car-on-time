/**
 * Сайт помнит клиента — только в браузере (localStorage), без регистрации и без отправки на сервер:
 * просмотренные машины, «похоже на то, что вы смотрели», продолжение подбора. Даты — в daterange.js.
 */
const VIEWED_KEY = 'memory:viewed';
const QUIZ_KEY = 'memory:quiz';
const LIMIT = 12;

const read = (key, fallback) => {
    try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback; } catch { return fallback; }
};
const write = (key, value) => {
    try { localStorage.setItem(key, JSON.stringify(value)); } catch { /* приватный режим */ }
};
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const money = (n) => Number(n).toLocaleString('ru-RU');

function card(car) {
    return `<a class="mini-car" href="${esc(car.url)}">
        <span class="mini-car-media">${car.thumb ? `<img src="${esc(car.thumb)}" alt="" width="160" height="88" loading="lazy" decoding="async">` : ''}</span>
        <span class="mini-car-name" data-tooltip-overflow>${esc(car.name)}</span>
        ${car.meta ? `<span class="mini-car-meta">${esc(car.meta)}</span>` : ''}
        ${car.price ? `<span class="mini-car-price">от ${money(car.price)} ₽<small>/сут</small></span>` : ''}
    </a>`;
}

// Закрытые крестиком блоки: запоминаем «что именно» закрыли — новые даты или новый подбор покажут блок снова
const DISMISS_KEY = 'memory:dismissed';
const dismissed = () => read(DISMISS_KEY, {});
function dismissable(el, name, signature) {
    if (!el || dismissed()[name] === signature) return false;
    el.querySelector('[data-dismiss]')?.addEventListener('click', () => {
        write(DISMISS_KEY, { ...dismissed(), [name]: signature });
        el.classList.add('is-closing');
        setTimeout(() => { el.hidden = true; el.classList.remove('is-closing'); }, 220);
    }, { once: true });
    return true;
}

export function initMemory() {
    // Запоминаем открытую машину
    const viewedEl = document.querySelector('[data-viewed-car]');
    if (viewedEl) {
        try {
            const car = JSON.parse(viewedEl.textContent);
            const list = read(VIEWED_KEY, []).filter((c) => c && c.id !== car.id);
            write(VIEWED_KEY, [{ ...car, at: Date.now() }, ...list].slice(0, LIMIT));
        } catch { /* битый JSON — пропускаем */ }
    }

    // Запоминаем результат подбора
    const quiz = document.querySelector('[data-quiz-result]');
    if (quiz) write(QUIZ_KEY, { url: location.pathname + location.search, summary: quiz.dataset.summary, at: Date.now() });

    document.querySelectorAll('[data-memory]').forEach((box) => {
        const exclude = Number(box.dataset.exclude) || null;
        const viewed = read(VIEWED_KEY, []).filter((c) => c && c.id !== exclude);

        const recent = box.querySelector('[data-recent]');
        if (recent && viewed.length) {
            recent.querySelector('[data-recent-list]').innerHTML = viewed.slice(0, 8).map(card).join('');
            recent.hidden = false;
            recent.querySelector('[data-recent-clear]')?.addEventListener('click', () => {
                write(VIEWED_KEY, []);
                recent.hidden = true;
                box.querySelector('[data-similar-block]')?.setAttribute('hidden', '');
            });
        }

        const similarBlock = box.querySelector('[data-similar-block]');
        if (similarBlock && box.dataset.similar && viewed.length) {
            const ids = viewed.slice(0, 8).map((c) => c.id).join(',');
            fetch(`${box.dataset.similar}?ids=${ids}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.cars?.length) return;
                    similarBlock.querySelector('[data-similar-list]').innerHTML = data.cars.map(card).join('');
                    similarBlock.hidden = false;
                })
                .catch(() => {});
        }

        // Приветствие: «С возвращением! Ваши даты — 27–30 сен. [Показать цены]» или «Вы смотрели X»
        const welcome = box.querySelector('[data-welcome]');
        if (welcome) {
            let dates = null;
            try { dates = JSON.parse(localStorage.getItem('trip:dates') || 'null'); } catch { dates = null; }
            const text = welcome.querySelector('[data-welcome-text]');
            const link = welcome.querySelector('[data-welcome-link]');
            const parse = (v) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; };
            const start = parse(dates?.start);
            const end = parse(dates?.end);
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const fresh = dates && Date.now() - dates.savedAt < 7 * 86400000 && start && start >= today;
            const fmt = (d) => d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }).replace('.', '');
            if (fresh && end && dismissable(welcome, 'welcome', `dates:${dates.start}:${dates.end}`)) {
                const days = Math.round((end - start) / 86400000);
                text.textContent = `Ваши даты — ${fmt(start)} – ${fmt(end)}, ${days} ${days % 10 === 1 && days % 100 !== 11 ? 'сутки' : 'суток'}.`;
                link.textContent = 'Показать цены на эти даты →';
                link.href = '/katalog';
                welcome.hidden = false;
            } else if (!(fresh && end) && viewed.length && dismissable(welcome, 'welcome', `viewed:${viewed[0].id}`)) {
                text.textContent = `Вы смотрели ${viewed[0].name}.`;
                link.textContent = 'Вернуться к машине →';
                link.href = viewed[0].url;
                welcome.hidden = false;
            }
        }

        const resume = box.querySelector('[data-quiz-resume]');
        const lastQuiz = read(QUIZ_KEY, null);
        if (resume && box.dataset.resume === '1' && lastQuiz?.url && Date.now() - lastQuiz.at < 14 * 86400000 && dismissable(resume, 'quiz', lastQuiz.url)) {
            resume.querySelector('[data-quiz-resume-link]').href = lastQuiz.url;
            resume.querySelector('[data-quiz-resume-text]').textContent = lastQuiz.summary || '';
            resume.hidden = false;
        }
    });
}
