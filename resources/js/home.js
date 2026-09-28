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

export function initHome() {
    initTabs();
    initHomeBar();
}
