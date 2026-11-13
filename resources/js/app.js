/*
 * Dates relatives (UXUI §6.2, TRD §20) : les dates arrivent en UTC dans
 * <time datetime="…" data-relative-date> et sont affichées dans la langue de la page
 * et le fuseau du navigateur. Chiffres occidentaux dans toutes les langues (UXUI D-09).
 */
const UNITS = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
    ['second', 1],
];

function formatRelativeDates(root = document) {
    const lang = `${document.documentElement.lang || 'en'}-u-nu-latn`;
    const relative = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
    const absolute = new Intl.DateTimeFormat(lang, { dateStyle: 'long', timeStyle: 'short' });

    root.querySelectorAll('time[data-relative-date]').forEach((element) => {
        const date = new Date(element.getAttribute('datetime'));

        if (Number.isNaN(date.getTime())) {
            return;
        }

        const seconds = Math.round((date.getTime() - Date.now()) / 1000);
        const [unit, size] = UNITS.find(([, length]) => Math.abs(seconds) >= length) ?? ['second', 1];

        element.textContent = relative.format(Math.round(seconds / size), unit);
        element.title = absolute.format(date);
    });
}

document.addEventListener('DOMContentLoaded', () => formatRelativeDates());
document.addEventListener('livewire:navigated', () => formatRelativeDates());
