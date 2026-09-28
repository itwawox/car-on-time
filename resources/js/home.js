/**
 * Главная: вкладки «Популярные машины» и нижняя панель действия на телефоне.
 */
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const tabs = [...root.querySelectorAll('[role="tab"]')];
        const select = (tab, focus = false) => {
            tabs.forEach((t) => {
                const on = t === tab;
                t.setAttribute('aria-selected', String(on));
                t.tabIndex = on ? 0 : -1;
                const panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) {
                    panel.hidden = !on;
                    if (on) {
                        panel.classList.remove('is-entering');
                        void panel.offsetWidth;
                        panel.classList.add('is-entering');
                    }
                }
            });
            if (focus) tab.focus();
        };
        tabs.forEach((tab, i) => {
            tab.addEventListener('click', () => select(tab));
            tab.addEventListener('keydown', (e) => {
                const d = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
                if (!d) return;
                e.preventDefault();
                select(tabs[(i + d + tabs.length) % tabs.length], true);
            });
        });
    });
}

function initHomeBar() {
    const bar = document.querySelector('[data-home-bar]');
    const form = document.getElementById('panel-dates');
    if (!bar || !form || !('IntersectionObserver' in window)) return;
    const footer = document.querySelector('.site-footer');
    let formVisible = true;
    let footerVisible = false;
    const update = () => {
        const show = !formVisible && !footerVisible;
        bar.classList.toggle('is-hidden', !show);
        document.body.classList.toggle('has-sticky-bar', show);
        // Пока на экране форма первого экрана, плавающая кнопка чата не закрывает «Показать машины»
        document.body.classList.toggle('fab-quiet', formVisible);
    };
    new IntersectionObserver(([e]) => { formVisible = e.isIntersecting; update(); }).observe(form);
    if (footer) new IntersectionObserver(([e]) => { footerVisible = e.isIntersecting; update(); }).observe(footer);

    bar.querySelector('[data-home-bar-dates]')?.addEventListener('click', (e) => {
        e.preventDefault();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => form.querySelector('.daterange-trigger, input')?.focus({ preventScroll: true }), 500);
    });
}

// «Сцена» первого экрана: вкладка задачи меняет машину, подпись и ссылку — только по нажатию, без карусели
function initHeroStage() {
    const stage = document.querySelector('[data-hero-stage]');
    if (!stage) return;
    const img = stage.querySelector('[data-stage-img]');
    const title = stage.querySelector('[data-stage-title]');
    const meta = stage.querySelector('[data-stage-meta]');
    const links = stage.querySelectorAll('[data-stage-link]');
    const buttons = stage.querySelectorAll('[data-stage-slide]');
    const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Фото остальных задач подгружаем заранее — смена без пустого кадра
    const preload = () => buttons.forEach((b) => { new Image().src = JSON.parse(b.dataset.stageSlide).image; });
    ('requestIdleCallback' in window ? requestIdleCallback : setTimeout)(preload);

    const show = (slide) => {
        img.src = slide.image;
        img.alt = slide.alt;
        img.classList.toggle('is-cutout', slide.cutout);
        title.textContent = slide.title;
        meta.textContent = slide.meta;
        links.forEach((a) => { a.href = slide.url; });
    };

    buttons.forEach((button) => button.addEventListener('click', () => {
        if (button.getAttribute('aria-pressed') === 'true') return;
        buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
        const slide = JSON.parse(button.dataset.stageSlide);
        if (reduceMotion) { show(slide); return; }
        img.classList.add('is-leaving');
        setTimeout(() => {
            show(slide);
            requestAnimationFrame(() => img.classList.remove('is-leaving'));
        }, 220);
    }));
}

// «Как это работает»: шаги загораются по очереди, когда блок появился на экране — один раз
function initStepsFlow() {
    const list = document.querySelector('[data-steps-flow]');
    if (!list) return;
    if (!('IntersectionObserver' in window)) { list.classList.add('is-lit'); return; }
    const io = new IntersectionObserver(([entry]) => {
        if (!entry.isIntersecting) return;
        list.classList.add('is-lit');
        io.disconnect();
    }, { threshold: 0.6 });
    io.observe(list);
}

export function initHome() {
    initTabs();
    initHomeBar();
    initHeroStage();
    initStepsFlow();
}
