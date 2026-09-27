/**
 * Вопросы и ответы: живой поиск (без учёта регистра и ё), открытие вопроса по ссылке #q-…,
 * подсветка раздела в навигации, «Ссылка на вопрос».
 */
const norm = (s) => String(s).toLowerCase().replace(/ё/g, 'е');

export function initFaq() {
    const page = document.querySelector('[data-faq-page]');

    // Открыть вопрос по ссылке — работает на любой странице с FAQ
    const openHash = () => {
        const id = decodeURIComponent(location.hash.slice(1));
        const el = id ? document.getElementById(id) : null;
        if (el?.matches('details')) {
            el.open = true;
            el.scrollIntoView({ block: 'center' });
            el.classList.add('is-highlight');
            setTimeout(() => el.classList.remove('is-highlight'), 1800);
        }
    };
    openHash();
    window.addEventListener('hashchange', openHash);

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-copy-link]');
        if (!btn) return;
        const url = location.origin + location.pathname + btn.dataset.copyLink;
        try {
            await navigator.clipboard.writeText(url);
            btn.classList.add('is-copied');
            const old = btn.lastChild.textContent;
            btn.lastChild.textContent = ' Скопировано';
            setTimeout(() => { btn.classList.remove('is-copied'); btn.lastChild.textContent = old; }, 1600);
        } catch {
            history.replaceState(null, '', btn.dataset.copyLink);
        }
    });

    if (!page) return;
    const input = document.querySelector('[data-faq-search]');
    // Совпадение с начала слова: «мост» находит «мост», «мосту», но не «стоимость»
    const items = [...page.querySelectorAll('[data-faq-item]')].map((el) => ({ el, words: norm(el.textContent).split(/[^a-zа-я0-9]+/).filter(Boolean) }));
    const groups = [...page.querySelectorAll('[data-faq-group]')];
    const empty = page.querySelector('[data-faq-empty]');

    input?.addEventListener('input', () => {
        const words = norm(input.value).split(/[^a-zа-я0-9]+/).filter((w) => w.length > 1 || /\d/.test(w));
        let shown = 0;
        items.forEach(({ el, words: itemWords }) => {
            const hit = !words.length || words.every((w) => itemWords.some((iw) => iw.startsWith(w)));
            el.hidden = !hit;
            if (words.length && hit) el.open = shown < 3;
            if (!words.length) el.open = false;
            shown += hit ? 1 : 0;
        });
        groups.forEach((g) => { g.hidden = !g.querySelector('[data-faq-item]:not([hidden])'); });
        if (empty) empty.hidden = shown > 0;
    });

    // Подсветка текущего раздела в навигации
    const links = new Map([...page.querySelectorAll('[data-faq-nav]')].map((a) => [a.dataset.faqNav, a]));
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                links.forEach((a) => a.removeAttribute('aria-current'));
                links.get(entry.target.id.replace('g-', ''))?.setAttribute('aria-current', 'true');
            });
        }, { rootMargin: '-30% 0px -60% 0px' });
        groups.forEach((g) => io.observe(g));
    }
}
