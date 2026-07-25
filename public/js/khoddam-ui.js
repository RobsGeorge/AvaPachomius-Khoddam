(function () {
    const THEME_KEY = 'khoddam_theme';

    function readJson(id, fallback) {
        const node = document.getElementById(id);
        if (!node) {
            return fallback;
        }
        try {
            return JSON.parse(node.textContent || 'null') ?? fallback;
        } catch {
            return fallback;
        }
    }

    const config = readJson('khoddam-ui-config', {});

    function getStoredTheme() {
        return localStorage.getItem(THEME_KEY) || getCookie('theme') || 'light';
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    function swalPopupClass(extra = '') {
        return ['khoddam-swal-popup', 'animate__animated', 'animate__fadeInUp', 'animate__faster', extra]
            .filter(Boolean)
            .join(' ');
    }

    function baseSwalOptions(extraPopupClass = '') {
        return {
            buttonsStyling: true,
            // Keep the underlying page layout stable when a dialog opens (no body
            // resize / scrollbar-padding shift).
            heightAuto: false,
            scrollbarPadding: false,
            // Transparent backdrop (never backdrop:false): false leaves a full-screen
            // .swal2-container that can block clicks after Cancel/Escape.
            backdrop: 'rgba(0,0,0,0)',
            didClose: cleanupAfterSwal,
            customClass: {
                popup: swalPopupClass(extraPopupClass),
                title: 'khoddam-swal-title',
                htmlContainer: 'khoddam-swal-body',
                confirmButton: 'btn btn-sm',
                cancelButton: 'btn btn-sm',
            },
            showClass: { popup: '' },
            hideClass: { popup: '' },
        };
    }

    function toastIcon(type) {
        const map = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info',
            question: 'question',
        };
        return map[type] || 'info';
    }

    function toastTitle(type) {
        const map = {
            success: config.toastSuccess || 'Done',
            error: config.toastError || 'Error',
            warning: config.toastWarning || 'Warning',
            info: config.toastInfo || 'Notice',
        };
        return map[type] || map.info;
    }

    function baseToastOptions() {
        return {
            buttonsStyling: false,
            // Prevent SweetAlert2 from resizing <html>/<body> or adding scrollbar
            // padding, which otherwise pushes centred layouts (e.g. login) down and
            // introduces a scroll when a toast appears.
            heightAuto: false,
            scrollbarPadding: false,
            customClass: {
                popup: 'khoddam-swal-popup khoddam-swal-toast',
                title: 'khoddam-swal-title',
                closeButton: 'khoddam-swal-toast-close',
            },
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
        };
    }

    function announce(message) {
        // Mirror transient messages into the polite live region (A11Y-03) so screen
        // readers announce them even though the visible toast is a SweetAlert popup.
        const region = document.getElementById('khoddam-live-region');
        if (!region || !message) {
            return;
        }
        region.textContent = '';
        // Re-set on the next frame so identical consecutive messages still announce.
        window.requestAnimationFrame(() => {
            region.textContent = String(message);
        });
    }

    function showToast(message, type = 'success') {
        if (!message || typeof Swal === 'undefined') {
            return;
        }

        announce(message);

        const Toast = Swal.mixin({
            ...baseToastOptions(),
            toast: true,
            position: config.dir === 'rtl' ? 'top-start' : 'top-end',
            showConfirmButton: false,
            showCloseButton: true,
            timer: type === 'error' ? 6000 : 4500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });

        Toast.fire({
            icon: toastIcon(type),
            title: String(message),
        });
    }

    function confirmDialog(message, options = {}) {
        if (typeof Swal === 'undefined') {
            return Promise.resolve(window.confirm(message || config.confirmTitle || 'Are you sure?'));
        }

        const destructive = options.destructive === true;

        return Swal.fire({
            ...baseSwalOptions(destructive ? 'khoddam-swal-destructive' : ''),
            icon: destructive ? 'warning' : 'question',
            title: options.title || (destructive ? (config.deleteTitle || config.confirmTitle) : config.confirmTitle),
            text: message || '',
            showCancelButton: true,
            confirmButtonText: options.confirmText || (destructive ? (config.deleteYes || config.confirmYes) : config.confirmYes),
            cancelButtonText: options.cancelText || config.confirmCancel,
            reverseButtons: config.dir === 'rtl',
            focusCancel: destructive,
            allowOutsideClick: false,
            allowEscapeKey: true,
        }).then((result) => {
            cleanupAfterSwal();
            return result.isConfirmed;
        }).catch(() => {
            cleanupAfterSwal();
            return false;
        });
    }

    function promptDialog(message, options = {}) {
        if (typeof Swal === 'undefined') {
            const value = window.prompt(message || config.promptTitle || 'Enter value');
            return Promise.resolve(value);
        }

        return Swal.fire({
            ...baseSwalOptions(),
            icon: 'question',
            title: options.title || config.promptTitle,
            text: message || '',
            input: 'text',
            inputPlaceholder: options.placeholder || config.promptPlaceholder,
            inputValue: options.value || '',
            showCancelButton: true,
            confirmButtonText: options.confirmText || config.promptSubmit || config.confirmYes,
            cancelButtonText: options.cancelText || config.confirmCancel,
            reverseButtons: config.dir === 'rtl',
            allowOutsideClick: false,
            allowEscapeKey: true,
            inputValidator: (value) => {
                if (options.required !== false && !value) {
                    return options.requiredMessage || config.promptPlaceholder;
                }
                return undefined;
            },
        }).then((result) => {
            cleanupAfterSwal();
            return result.isConfirmed ? result.value : null;
        }).catch(() => {
            cleanupAfterSwal();
            return null;
        });
    }

    function showValidationErrors(errors) {
        if (!errors?.length) {
            return;
        }

        if (errors.length === 1) {
            showToast(errors[0], 'error');
            return;
        }

        if (typeof Swal === 'undefined') {
            window.alert(errors.join('\n'));
            return;
        }

        const html = `<ul class="text-start mb-0 ps-3">${errors.map((e) => `<li>${escapeHtml(e)}</li>`).join('')}</ul>`;

        Swal.fire({
            ...baseSwalOptions(),
            icon: 'error',
            title: config.validationTitle || 'Validation',
            html,
            confirmButtonText: config.confirmYes || 'OK',
            allowOutsideClick: true,
            allowEscapeKey: true,
            showCloseButton: true,
        }).then(() => cleanupAfterSwal()).catch(() => cleanupAfterSwal());
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function extractConfirmMessage(source) {
        if (!source) {
            return '';
        }

        // Message already lives on data-confirm; nothing to extract from the handler body.
        if (/confirm\s*\(\s*this\.dataset\.confirm\s*\)/.test(source)) {
            return '';
        }

        // Prefer quoted string args (covers Blade @json(...) and '{{ ... }}' after render).
        const quoted = source.match(/confirm\s*\(\s*(["'`])([\s\S]*?)\1\s*\)/);
        if (quoted) {
            return quoted[2];
        }

        return '';
    }

    function isDestructiveAction(form, message) {
        if (form.dataset.destructive === 'true') {
            return true;
        }
        const method = (form.querySelector('[name="_method"]')?.value || form.method || 'post').toLowerCase();
        if (method === 'delete') {
            return true;
        }
        const text = `${message} ${form.action}`.toLowerCase();
        return /delete|destroy|remove|cancel|flush|حذف|إلغاء/.test(text);
    }

    function releaseSwalBodyLock() {
        const html = document.documentElement;
        const body = document.body;
        ['swal2-shown', 'swal2-height-auto', 'swal2-no-backdrop'].forEach((cls) => {
            html.classList.remove(cls);
            body.classList.remove(cls);
        });
        body.style.removeProperty('padding-right');
        body.style.removeProperty('overflow');
        html.style.removeProperty('overflow');
    }

    function cleanupAfterSwal() {
        releaseSwalBodyLock();

        // Drop any stuck non-toast containers that still capture pointer events.
        document.querySelectorAll('body > .swal2-container').forEach((el) => {
            if (!el.querySelector('.swal2-toast')) {
                el.remove();
            }
        });
    }

    function submitConfirmedForm(form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (typeof Swal !== 'undefined') {
            Swal.close();
        }

        cleanupAfterSwal();

        // Native submit bypasses submit listeners (avoids SweetAlert re-intercept loop).
        form.submit();
    }

    function migrateInlineConfirmHandlers(root = document) {
        root.querySelectorAll('form[onsubmit]').forEach((form) => {
            const code = form.getAttribute('onsubmit') || '';
            if (!/confirm\s*\(/.test(code)) {
                return;
            }

            if (!form.dataset.confirm) {
                const extracted = extractConfirmMessage(code);
                if (extracted) {
                    form.dataset.confirm = extracted;
                }
            }

            // Only strip the native handler once we have a Swal-bound message.
            if (form.dataset.confirm) {
                form.removeAttribute('onsubmit');
            }
        });

        root.querySelectorAll('button[onclick*="confirm"], input[onclick*="confirm"]').forEach((el) => {
            const code = el.getAttribute('onclick') || '';
            if (!/confirm\s*\(/.test(code)) {
                return;
            }

            if (!el.dataset.confirm) {
                const extracted = extractConfirmMessage(code);
                if (extracted) {
                    el.dataset.confirm = extracted;
                }
            }

            if (el.dataset.confirm || el.closest('form')?.dataset.confirm) {
                el.dataset.khoddamConfirmButton = '1';
                el.removeAttribute('onclick');
            }
        });
    }

    function initConfirmInterceptors() {
        migrateInlineConfirmHandlers();

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            const message = form.dataset.confirm || submitter?.dataset?.confirm || '';
            if (!message) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            confirmDialog(message, { destructive: isDestructiveAction(form, message) }).then((ok) => {
                if (!ok) {
                    return;
                }
                submitConfirmedForm(form);
            });
        }, true);

        document.addEventListener('click', (event) => {
            const button = event.target.closest(
                '[data-khoddam-confirm-button="1"], button[type="submit"][data-confirm], input[type="submit"][data-confirm]'
            );
            if (!button) {
                return;
            }

            const form = button.closest('form');
            if (!form) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const message = button.dataset.confirm || form.dataset.confirm || config.confirmTitle;
            confirmDialog(message, { destructive: isDestructiveAction(form, message) }).then((ok) => {
                if (!ok) {
                    return;
                }
                submitConfirmedForm(form);
            });
        }, true);

        if (typeof MutationObserver !== 'undefined') {
            let moTimer = null;
            const mo = new MutationObserver(() => {
                if (moTimer) {
                    clearTimeout(moTimer);
                }
                moTimer = setTimeout(() => migrateInlineConfirmHandlers(), 200);
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }
    }

    function initFlashMessages() {
        const flash = readJson('khoddam-flash-messages', {});

        if (flash.success) {
            showToast(flash.success, 'success');
        }
        if (flash.error) {
            showToast(flash.error, 'error');
        }
        if (flash.warning) {
            showToast(flash.warning, 'warning');
        }
        if (flash.info) {
            showToast(flash.info, 'info');
        }
        if (flash.status) {
            showToast(flash.status, 'info');
        }
        if (flash.validation) {
            showValidationErrors(flash.validation);
        }

        document.querySelectorAll('[data-khoddam-flash]').forEach((node) => {
            node.remove();
        });
    }

    function applyTheme(theme) {
        const mode = theme === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', mode);
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add(mode === 'dark' ? 'theme-dark' : 'theme-light');
        localStorage.setItem(THEME_KEY, mode);

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const isDark = mode === 'dark';
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            const label = btn.querySelector('[data-theme-label]');
            if (label) {
                label.textContent = isDark
                    ? (btn.dataset.labelLight || 'Light')
                    : (btn.dataset.labelDark || 'Dark');
            }
        });
    }

    function persistTheme(theme) {
        fetch('/theme', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ theme }),
        }).catch(() => {});
    }

    function initTheme() {
        applyTheme(getStoredTheme());

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
                applyTheme(next);
                persistTheme(next);
            });
        });
    }

    function initReveal() {
        const nodes = document.querySelectorAll('.app-card, .app-tile, .hub-tile, .hub-link-tile, .animate-in');
        nodes.forEach((el, index) => {
            el.style.animationDelay = `${Math.min(index * 0.12, 0.84)}s`;
        });

        document.querySelectorAll('.accordion-item, .roles-hub-panel').forEach((el, index) => {
            el.style.animationDelay = `${Math.min(index * 0.1, 0.7)}s`;
        });
    }

    function initHoverMotion() {
        document.querySelectorAll('.btn, .nav-link, .hub-link-tile, .hub-tile, .app-tile, .announcement-home-card').forEach((el) => {
            el.classList.add('khoddam-hover-lift');
        });
    }

    /**
     * Theme-colored icon emphasis on mobile when widgets scroll into view
     * or receive a light touch (hover is unreliable on touch screens).
     */
    function initIconAccent() {
        const iconHostSelector = [
            'a',
            'button',
            '.btn',
            'label.btn',
            '.brand-link',
            '.app-nav-link',
            '.app-toolbar-btn',
            '.app-dropdown-link',
            '.hub-tile',
            '.hub-link-tile',
            '.app-tile',
            '.khoddam-hover-lift',
            '.accordion-button',
            'summary',
            '.nav-link',
            '.list-group-item-action',
            '.dropdown-item',
            '.dropdown-toggle',
            '[role="button"]',
        ].join(',');

        const iconChildSelector = 'i.bi, i.fas, i.far, i.fab, i.fa, .app-icon';

        function hostsWithIcons(root) {
            return Array.from((root || document).querySelectorAll(iconHostSelector)).filter((el) => {
                if (el.closest('.swal2-container, .modal-backdrop')) {
                    return false;
                }

                return el.querySelector(iconChildSelector);
            });
        }

        function prefersTouchEmphasis() {
            return window.matchMedia('(hover: none), (pointer: coarse)').matches
                || window.matchMedia('(max-width: 767.98px)').matches;
        }

        let observer = null;

        function syncObserver() {
            if (observer) {
                observer.disconnect();
                observer = null;
            }

            if (! prefersTouchEmphasis()) {
                hostsWithIcons().forEach((el) => el.classList.remove('is-icon-accent'));
                return;
            }

            observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    const active = entry.isIntersecting && entry.intersectionRatio >= 0.4;
                    entry.target.classList.toggle('is-icon-accent', active);
                });
            }, {
                threshold: [0.35, 0.45, 0.6, 0.75],
                rootMargin: '-8% 0px -8% 0px',
            });

            hostsWithIcons().forEach((el) => observer.observe(el));
        }

        function bindTouch(el) {
            if (el.dataset.iconAccentBound === '1') {
                return;
            }
            el.dataset.iconAccentBound = '1';

            let touchTimer = null;

            el.addEventListener('touchstart', () => {
                if (! prefersTouchEmphasis()) {
                    return;
                }
                el.classList.add('is-icon-accent-touch');
                if (touchTimer) {
                    clearTimeout(touchTimer);
                    touchTimer = null;
                }
            }, { passive: true });

            const clearTouch = () => {
                if (touchTimer) {
                    clearTimeout(touchTimer);
                }
                // Brief linger so a light tap (without full click) still shows the fill.
                touchTimer = setTimeout(() => {
                    el.classList.remove('is-icon-accent-touch');
                    touchTimer = null;
                }, 550);
            };

            el.addEventListener('touchend', clearTouch, { passive: true });
            el.addEventListener('touchcancel', clearTouch, { passive: true });
        }

        function refresh() {
            hostsWithIcons().forEach(bindTouch);
            syncObserver();
        }

        refresh();

        let resizeTimer = null;
        window.addEventListener('resize', () => {
            if (resizeTimer) {
                clearTimeout(resizeTimer);
            }
            resizeTimer = setTimeout(refresh, 200);
        }, { passive: true });

        // Re-bind when hubs/modals inject content after first paint.
        if (typeof MutationObserver !== 'undefined') {
            let moTimer = null;
            const mo = new MutationObserver(() => {
                if (moTimer) {
                    clearTimeout(moTimer);
                }
                moTimer = setTimeout(refresh, 250);
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initFlashMessages();
        initConfirmInterceptors();
        initReveal();
        initHoverMotion();
        initIconAccent();
    });

    window.KhoddamUI = {
        applyTheme,
        toast: showToast,
        confirm: confirmDialog,
        prompt: promptDialog,
        validation: showValidationErrors,
    };
})();
