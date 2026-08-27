const STORAGE_KEY = 'muebledesk-theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

const normalizeTheme = (theme) => ['light', 'dark', 'system'].includes(theme) ? theme : 'light';
const forcedTheme = document.documentElement.dataset.forceTheme;

const applyTheme = (theme) => {
    const selectedTheme = forcedTheme === 'light' ? 'light' : normalizeTheme(theme);
    const shouldUseDark = selectedTheme === 'dark' || (selectedTheme === 'system' && media.matches);

    document.documentElement.classList.toggle('dark', shouldUseDark);
    document.documentElement.dataset.theme = selectedTheme;
    document.documentElement.style.colorScheme = shouldUseDark ? 'dark' : 'light';

    return selectedTheme;
};

const getStoredTheme = () => normalizeTheme(localStorage.getItem(STORAGE_KEY) || 'light');

const setStoredTheme = (theme) => {
    const selectedTheme = normalizeTheme(theme);
    localStorage.setItem(STORAGE_KEY, selectedTheme);
    applyTheme(selectedTheme);

    window.dispatchEvent(new CustomEvent('mueble-theme-changed', { detail: selectedTheme }));
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: selectedTheme } }));
};

window.setTheme = setStoredTheme;
window.getTheme = getStoredTheme;
window.muebleTheme = {
    get: getStoredTheme,
    set: setStoredTheme,
};

applyTheme(getStoredTheme());

media.addEventListener('change', () => {
    if (forcedTheme !== 'light' && getStoredTheme() === 'system') {
        applyTheme('system');
        window.dispatchEvent(new CustomEvent('mueble-theme-changed', { detail: 'system' }));
    }
});
