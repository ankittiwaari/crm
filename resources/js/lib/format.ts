const inr = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    maximumFractionDigits: 2,
    minimumFractionDigits: 0,
});

/**
 * Format an amount stored in paise as Indian rupees, e.g. ₹1,25,000.
 */
export function formatInr(paise: number): string {
    return inr.format(paise / 100);
}

/**
 * Show an E.164 Indian number as +91 98450 12345.
 */
export function formatPhone(e164: string | null): string {
    if (!e164) {
        return '';
    }

    const match = /^\+91(\d{5})(\d{5})$/.exec(e164);

    return match ? `+91 ${match[1]} ${match[2]}` : e164;
}

/**
 * A wa.me click-to-chat link, optionally with a prefilled message.
 */
export function whatsappLink(e164: string, text?: string): string {
    const query = text ? `?text=${encodeURIComponent(text)}` : '';

    return `https://wa.me/${e164.replace(/\D/g, '')}${query}`;
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('en-IN', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

const relative = new Intl.RelativeTimeFormat('en-IN', { numeric: 'auto' });

/**
 * "2 days ago", "yesterday", "in 3 weeks".
 */
export function timeAgo(value: string | null, now: Date = new Date()): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round(
        (new Date(value).getTime() - now.getTime()) / 1000,
    );
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter((word) => /^[A-Za-z0-9]/.test(word))
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
}

/**
 * yyyy-mm-dd for a date input, `days` from today.
 */
export function isoDateFromToday(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
