{{-- «Перелёт» фото машины между списком и страницей машины (View Transitions между страницами).
     Имя car-photo получает только одна пара: карточка, по которой ушли (pageswap), и галерея — или галерея
     и карточка той же машины при возврате (pagereveal). Повтор имени браузер считает ошибкой и отменяет переход,
     поэтому статических имён у карточек нет. Скрипт встроен в <head>: pagereveal срабатывает до модулей. --}}
<script>
(function () {
    var NAME = 'car-photo';
    var CARDS = '.car-card, .mini-car, .alt, .compare-car, .qr-best';
    var MEDIA = '.car-card-media, .mini-car-media, .alt-media, .compare-car-media, .qr-best-media';
    function media(path) {
        var best = null;
        document.querySelectorAll('a[href]').forEach(function (a) {
            if (best && best.inView) return;
            var url; try { url = new URL(a.href, location.href); } catch (e) { return; }
            if (url.origin !== location.origin || url.pathname !== path) return;
            var card = a.closest(CARDS);
            var m = card && card.querySelector(MEDIA);
            if (!m || !m.getClientRects().length) return;
            var r = m.getBoundingClientRect();
            var inView = r.bottom > 0 && r.top < innerHeight;
            if (!best || inView) best = { el: m, inView: inView };
        });
        return best && best.el;
    }
    function name(el, finished) {
        document.querySelectorAll('[data-vt]').forEach(function (n) { n.style.viewTransitionName = ''; n.removeAttribute('data-vt'); });
        el.style.viewTransitionName = NAME;
        el.setAttribute('data-vt', '');
        if (finished) finished.finally(function () { el.style.viewTransitionName = ''; el.removeAttribute('data-vt'); });
    }
    function isCar(path) { return path.indexOf('/avto/') === 0; }
    // Уходим со страницы: в карточку машины — называем фото карточки, по которой нажали
    addEventListener('pageswap', function (e) {
        if (!e.viewTransition || !e.activation || !e.activation.entry) return;
        var to = new URL(e.activation.entry.url).pathname;
        var gallery = document.querySelector('[data-gallery-main]');
        // Со страницы машины в список: «улетает» фото галереи, в списке его примет карточка (pagereveal)
        if (!isCar(to)) { if (gallery) name(gallery); return; }
        if (to === location.pathname) return;
        var m = media(to);
        if (!m) { if (gallery && isCar(location.pathname)) gallery.style.viewTransitionName = 'none'; return; }
        if (gallery) gallery.style.viewTransitionName = 'none';
        name(m);
    });
    // Пришли на страницу: из карточки машины обратно в список — называем карточку той же машины
    addEventListener('pagereveal', function (e) {
        if (!e.viewTransition || !window.navigation || !navigation.activation || !navigation.activation.from) return;
        var gallery = document.querySelector('[data-gallery-main]');
        if (gallery) { gallery.style.viewTransitionName = NAME; return; }
        var from = new URL(navigation.activation.from.url).pathname;
        if (!isCar(from)) return;
        var m = media(from);
        if (m) name(m, e.viewTransition.finished);
    });
})();
</script>
