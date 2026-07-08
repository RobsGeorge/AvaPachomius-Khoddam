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
        const nodes = document.querySelectorAll('.app-card, .app-tile, .animate-in');
        nodes.forEach((el, index) => {
            el.style.animationDelay = `${Math.min(index * 0.06, 0.36)}s`;
        });
    }

    function buildThemeCssBlock(palette) {
        const lines = [];
        const modes = [
            ['light', 'body.theme-light'],
            ['dark', 'body.theme-dark'],
        ];

        modes.forEach(([mode, selector]) => {
            const colors = palette[mode] || {};
            const primary = colors.primary || '#7c3aed';
            const primaryHover = colors.primary_hover || primary;
            const title = colors.title || primary;
            const link = colors.link || primary;

            lines.push(`${selector} {`);
            lines.push(`    --color-primary: ${primary};`);
            lines.push(`    --color-primary-hover: ${primaryHover};`);
            lines.push(`    --color-title: ${title};`);
            lines.push(`    --color-title-accent: ${primary};`);
            lines.push(`    --color-link: ${link};`);
            lines.push(`    --color-link-hover: ${primaryHover};`);
            lines.push(`    --color-nav-active: ${primary};`);
            lines.push('}');
        });

        return lines.join('\n');
    }

    function applyThemePreview(palette) {
        let style = document.getElementById('portal-theme-preview');
        if (!style) {
            style = document.createElement('style');
            style.id = 'portal-theme-preview';
            document.head.appendChild(style);
        }
        style.textContent = buildThemeCssBlock(palette);
    }

    function clearThemePreview() {
        document.getElementById('portal-theme-preview')?.remove();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initReveal();
    });

    window.KhoddamUI = { applyTheme, applyThemePreview, clearThemePreview, buildThemeCssBlock };
})();
