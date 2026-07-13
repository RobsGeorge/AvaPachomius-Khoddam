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

    function showToast(message, type = 'success') {
        if (!message || typeof Swal === 'undefined') {
            return;
        }

        const Toast = Swal.mixin({
            ...baseSwalOptions(),
            toast: true,
            position: config.dir === 'rtl' ? 'top-start' : 'top-end',
            showConfirmButton: false,
            timer: type === 'error' ? 6500 : 4200,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.classList.add('animate__animated', 'animate__fadeInDown', 'animate__faster');
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
        }).then((result) => result.isConfirmed);
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
            inputValidator: (value) => {
                if (options.required !== false && !value) {
                    return options.requiredMessage || config.promptPlaceholder;
                }
                return undefined;
            },
        }).then((result) => (result.isConfirmed ? result.value : null));
    }

    function showValidationErrors(errors) {
        if (!errors?.length) {
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
            confirmButtonText: config.confirmCancel || 'OK',
        });
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

        const datasetMatch = source.match(/dataset\.confirm/);
        if (datasetMatch) {
            return '';
        }

        const patterns = [
            /confirm\s*\(\s*@json\(([^)]+)\)\s*\)/,
            /confirm\s*\(\s*this\.dataset\.confirm\s*\)/,
            /confirm\s*\(\s*([^)]+)\s*\)/,
        ];

        for (const pattern of patterns) {
            const match = source.match(pattern);
            if (!match) {
                continue;
            }
            let raw = match[1] || '';
            raw = raw.trim();
            if (raw === 'this.dataset.confirm') {
                return '';
            }
            if ((raw.startsWith("'") && raw.endsWith("'")) || (raw.startsWith('"') && raw.endsWith('"'))) {
                return raw.slice(1, -1);
            }
            if (raw.startsWith('`') && raw.endsWith('`')) {
                return raw.slice(1, -1);
            }
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

    function migrateInlineConfirmHandlers() {
        document.querySelectorAll('form[onsubmit]').forEach((form) => {
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

            form.removeAttribute('onsubmit');
        });

        document.querySelectorAll('button[onclick*="confirm"], input[onclick*="confirm"]').forEach((el) => {
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

            el.dataset.khoddamConfirmButton = '1';
            el.removeAttribute('onclick');
        });
    }

    function initConfirmInterceptors() {
        migrateInlineConfirmHandlers();

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (form.dataset.khoddamConfirmed === '1') {
                delete form.dataset.khoddamConfirmed;
                return;
            }

            const message = form.dataset.confirm;
            if (!message) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            confirmDialog(message, { destructive: isDestructiveAction(form, message) }).then((ok) => {
                if (!ok) {
                    return;
                }
                form.dataset.khoddamConfirmed = '1';
                form.requestSubmit();
            });
        }, true);

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-khoddam-confirm-button="1"]');
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
                form.dataset.khoddamConfirmed = '1';
                form.requestSubmit();
            });
        }, true);
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
            el.style.animationDelay = `${Math.min(index * 0.06, 0.36)}s`;
        });

        document.querySelectorAll('.accordion-item, .roles-hub-panel').forEach((el, index) => {
            el.style.animationDelay = `${Math.min(index * 0.06, 0.36)}s`;
        });
    }

    function initHoverMotion() {
        document.querySelectorAll('.btn, .nav-link, .hub-link-tile, .hub-tile, .app-tile, .announcement-home-card').forEach((el) => {
            el.classList.add('khoddam-hover-lift');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initFlashMessages();
        initConfirmInterceptors();
        initReveal();
        initHoverMotion();
    });

    window.KhoddamUI = {
        applyTheme,
        toast: showToast,
        confirm: confirmDialog,
        prompt: promptDialog,
        validation: showValidationErrors,
    };
})();
