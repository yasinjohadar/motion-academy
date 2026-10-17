/** Display helpers shared by the course admin pages. */

export function formatDuration(totalSeconds) {
    const seconds = Number(totalSeconds) || 0;
    if (seconds <= 0) {
        return '—';
    }
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    if (hours > 0) {
        return `${hours} س ${minutes} د`;
    }
    if (minutes > 0) {
        return rest > 0 ? `${minutes} د ${rest} ث` : `${minutes} د`;
    }
    return `${rest} ث`;
}

/** Visible text of an HTML string (for counters, placeholders and excerpts). */
export function plainText(html) {
    if (!html) {
        return '';
    }
    // DOMParser builds an inert document: unlike innerHTML on a live element, nothing
    // in it loads or runs (no <img onerror>).
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
}

export function formatPrice(amount, currency = 'USD') {
    try {
        return new Intl.NumberFormat('ar', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(Number(amount) || 0);
    } catch {
        return `${Number(amount) || 0} ${currency}`;
    }
}

export const CURRENCIES = [
    { value: 'USD', label: 'دولار أمريكي (USD)' },
    { value: 'SAR', label: 'ريال سعودي (SAR)' },
    { value: 'AED', label: 'درهم إماراتي (AED)' },
    { value: 'EGP', label: 'جنيه مصري (EGP)' },
    { value: 'KWD', label: 'دينار كويتي (KWD)' },
    { value: 'QAR', label: 'ريال قطري (QAR)' },
    { value: 'JOD', label: 'دينار أردني (JOD)' },
    { value: 'TRY', label: 'ليرة تركية (TRY)' },
    { value: 'EUR', label: 'يورو (EUR)' },
];

export const LANGUAGES = [
    { value: 'ar', label: 'العربية' },
    { value: 'en', label: 'الإنجليزية' },
    { value: 'fr', label: 'الفرنسية' },
    { value: 'tr', label: 'التركية' },
];
