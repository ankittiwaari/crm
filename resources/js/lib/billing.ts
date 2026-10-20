import type { BillingCycleValue } from '@/types';

const MONTHS: Record<BillingCycleValue, number | null> = {
    one_time: null,
    monthly: 1,
    quarterly: 3,
    half_yearly: 6,
    yearly: 12,
};

type Ymd = { year: number; month: number; day: number };

function parse(date: string): Ymd {
    const [year, month, day] = date.split('-').map(Number);

    return { year, month, day };
}

function format({ year, month, day }: Ymd): string {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function daysInMonth(year: number, month: number): number {
    return new Date(Date.UTC(year, month, 0)).getUTCDate();
}

/**
 * Mirrors Carbon's addMonthsNoOverflow: 31 Jan + 1 month = 28/29 Feb.
 */
function addMonths(date: string, months: number): string {
    const { year, month, day } = parse(date);
    const total = year * 12 + (month - 1) + months;
    const nextYear = Math.floor(total / 12);
    const nextMonth = (total % 12) + 1;

    return format({
        year: nextYear,
        month: nextMonth,
        day: Math.min(day, daysInMonth(nextYear, nextMonth)),
    });
}

function addDays(date: string, days: number): string {
    const { year, month, day } = parse(date);
    const result = new Date(Date.UTC(year, month - 1, day + days));

    return format({
        year: result.getUTCFullYear(),
        month: result.getUTCMonth() + 1,
        day: result.getUTCDate(),
    });
}

export function todayIso(): string {
    const now = new Date();

    return format({
        year: now.getFullYear(),
        month: now.getMonth() + 1,
        day: now.getDate(),
    });
}

/**
 * The billing period that contains `day`, counted in whole periods from the start date.
 * Mirrors App\Enums\BillingCycle::periodContaining().
 */
export function periodContaining(
    cycle: BillingCycleValue,
    start: string,
    day: string = todayIso(),
): { start: string; end: string } | null {
    const months = MONTHS[cycle];

    if (months === null || !/^\d{4}-\d{2}-\d{2}$/.test(start)) {
        return null;
    }

    let periods = 0;

    while (addMonths(start, months * (periods + 1)) <= day) {
        periods++;
    }

    return {
        start: addMonths(start, months * periods),
        end: addDays(addMonths(start, months * (periods + 1)), -1),
    };
}

export function perYear(cycle: BillingCycleValue): number {
    const months = MONTHS[cycle];

    return months === null ? 0 : 12 / months;
}

/**
 * How far through the current period we are, from 0 to 1.
 */
export function periodProgress(start: string, end: string): number {
    const from = Date.parse(start);
    const to = Date.parse(addDays(end, 1));
    const now = Date.parse(todayIso());

    return Math.min(1, Math.max(0, (now - from) / (to - from)));
}

/**
 * Converts a typed rupee amount ("48,000.50") to paise, or null when it is not a number.
 */
export function rupeesToPaise(value: string): number | null {
    const normalized = value.replace(/[,\s₹]/g, '');

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');

    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}
