/** Баннер cookie: «Только необходимые» / «Принять все». Выбор — в localStorage; «Настройки cookie» в подвале открывают снова. */
const KEY = 'cookie:consent';

export function initCookies() {
    const banner = document.querySelector('[data-cookie-banner]');
    if (!banner) return;
    let choice = null;
    try { choice = localStorage.getItem(KEY); } catch { choice = null; }
    const show = () => { banner.hidden = false; requestAnimationFrame(() => banner.classList.add('is-open')); };
    if (!choice) setTimeout(show, 600);

    banner.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-cookie-choice]');
        if (!btn) return;
        try { localStorage.setItem(KEY, btn.dataset.cookieChoice); } catch { /* приватный режим */ }
        banner.classList.remove('is-open');
        setTimeout(() => { banner.hidden = true; }, 250);
        if (btn.dataset.cookieChoice === 'all') window.dispatchEvent(new Event('cookie-consent'));
    });
    document.querySelectorAll('[data-cookie-settings]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); show(); }));
}
