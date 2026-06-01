(function () {
    const THEME_KEY = 'khoddam_theme';

    function getStoredTheme() {
        return localStorage.getItem(THEME_KEY) || getCookie('theme') || 'light';
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    function applyTheme(theme) {
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add(theme === 'dark' ? 'theme-dark' : 'theme-light');
        localStorage.setItem(THEME_KEY, theme);

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const isDark = theme === 'dark';
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
        const nodes = document.querySelectorAll('.app-card, .app-tile, .animate-in');
        nodes.forEach((el, index) => {
            el.style.animationDelay = `${Math.min(index * 0.06, 0.36)}s`;
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initReveal();
    });

    window.KhoddamUI = { applyTheme };
})();
