const applyTheme = (theme) => {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const shouldUseDark = theme === 'dark' || (theme === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', shouldUseDark);
    document.documentElement.dataset.theme = theme;
};

const getStoredTheme = () => localStorage.getItem('theme') || 'system';

window.setTheme = (theme) => {
    const selectedTheme = ['light', 'dark', 'system'].includes(theme) ? theme : 'system';

    localStorage.setItem('theme', selectedTheme);
    applyTheme(selectedTheme);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: selectedTheme } }));
};

window.getTheme = getStoredTheme;

applyTheme(getStoredTheme());

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getStoredTheme() === 'system') {
        applyTheme('system');
    }
});
