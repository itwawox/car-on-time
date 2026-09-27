/**
 * Формы: маска телефона +7 (___) ___-__-__, проверка на лету и защита от двойной отправки.
 */
function format(digits) {
    let d = digits.replace(/\D/g, '');
    if (d.startsWith('8')) d = '7' + d.slice(1);
    if (d && !d.startsWith('7')) d = '7' + d;
    d = d.slice(0, 11);
    const p = d.slice(1);
    if (!d) return '';
    let out = '+7';
    if (p.length) out += ' (' + p.slice(0, 3);
    if (p.length >= 3) out += ')';
    if (p.length > 3) out += ' ' + p.slice(3, 6);
    if (p.length > 6) out += '-' + p.slice(6, 8);
    if (p.length > 8) out += '-' + p.slice(8, 10);
    return out;
}

const complete = (value) => value.replace(/\D/g, '').length === 11;

export function initForms() {
    document.querySelectorAll('[data-phone-mask]').forEach((input) => {
        const error = input.getAttribute('aria-describedby') ? document.getElementById(input.getAttribute('aria-describedby')) : null;
        const setError = (text) => {
            input.toggleAttribute('aria-invalid', Boolean(text));
            if (text) input.setAttribute('aria-invalid', 'true');
            if (error) error.textContent = text || '';
        };

        if (input.value) input.value = format(input.value);
        input.addEventListener('focus', () => { if (!input.value) input.value = '+7 ('; });
        input.addEventListener('input', () => {
            const caretAtEnd = input.selectionStart === input.value.length;
            input.value = format(input.value);
            if (caretAtEnd) input.setSelectionRange(input.value.length, input.value.length);
            if (complete(input.value)) setError('');
        });
        input.addEventListener('blur', () => {
            if (input.value === '+7 (' || input.value === '+7') input.value = '';
            if (input.value && !complete(input.value)) setError('Проверьте номер: нужно 10 цифр после +7.');
        });
        input.form?.addEventListener('submit', (e) => {
            if (!complete(input.value)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                setError(input.value ? 'Проверьте номер: нужно 10 цифр после +7.' : 'Укажите телефон — по нему подтвердим наличие машины.');
                input.focus();
            }
        });
    });

    // «Перезвоните мне» в плавающей кнопке — без перезагрузки страницы
    document.querySelectorAll('[data-callback-form]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            if (e.defaultPrevented) return;
            e.preventDefault();
            const msg = form.querySelector('[data-callback-msg]');
            const button = form.querySelector('[data-submit]');
            button.disabled = true;
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json().catch(() => ({}));
                if (res.ok) {
                    form.innerHTML = `<p class="fab-callback-done">✓ ${form.dataset.thanks || 'Спасибо! Перезвоним в ближайшее время.'}</p>`;
                } else {
                    msg.textContent = data.errors?.phone?.[0] || data.message || 'Не получилось отправить. Позвоните нам или напишите в мессенджер.';
                    button.disabled = false;
                }
            } catch {
                msg.textContent = 'Нет связи. Позвоните нам или напишите в мессенджер.';
                button.disabled = false;
            }
        });
    });

    // Кнопка «скопировать промокод»
    document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(btn.dataset.copy); btn.classList.add('is-copied'); setTimeout(() => btn.classList.remove('is-copied'), 1500); } catch { /* нет доступа к буферу */ }
    }));

    // Одна отправка — одна заявка
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return;
            const button = form.querySelector('[data-submit]');
            if (!button) return;
            if (form.dataset.sending) { e.preventDefault(); return; }
            form.dataset.sending = '1';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            if (button.dataset.busyText) button.textContent = button.dataset.busyText;
        });
    });
    // Вернулись назад из истории — снова даём отправить
    window.addEventListener('pageshow', (e) => {
        if (!e.persisted) return;
        document.querySelectorAll('form[data-sending]').forEach((form) => {
            delete form.dataset.sending;
            const button = form.querySelector('[data-submit]');
            if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
        });
    });
}

// Загрузка документов: в плашке показываем имя выбранного файла
document.querySelectorAll('.doc-slot input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
        const slot = input.closest('.doc-slot');
        const state = slot?.querySelector('.doc-slot-state');
        if (state && input.files?.[0]) {
            state.textContent = `Выбрано: ${input.files[0].name}`;
            slot.classList.add('has-file');
        }
    });
});
