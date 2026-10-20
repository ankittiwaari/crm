import { Link, router } from '@inertiajs/react';
import {
    AlarmClock,
    Check,
    CircleDollarSign,
    MessageSquare,
    RefreshCw,
    Sparkles,
    Undo2,
} from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/ReminderController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Reminder, ReminderTypeValue } from '@/types';

const STYLES: Record<
    ReminderTypeValue,
    { icon: typeof Check; className: string }
> = {
    renewal: { icon: RefreshCw, className: 'bg-accent text-accent-foreground' },
    promo_review: {
        icon: Sparkles,
        className:
            'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300',
    },
    payment_follow_up: {
        icon: CircleDollarSign,
        className:
            'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
    },
    follow_up: {
        icon: MessageSquare,
        className:
            'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
    },
    general: {
        icon: AlarmClock,
        className: 'bg-muted text-muted-foreground',
    },
};

const SNOOZE = [
    { days: 1, label: 'Tomorrow' },
    { days: 3, label: 'In 3 days' },
    { days: 7, label: 'Next week' },
];

export function ReminderRow({ reminder }: { reminder: Reminder }) {
    const style = STYLES[reminder.type];
    const done = reminder.completed_at !== null;

    const change = (data: { done?: boolean; snooze_days?: number }) =>
        router.patch(update.url(reminder.id), data, {
            preserveScroll: true,
            preserveState: true,
        });

    return (
        <li className="flex flex-wrap items-center gap-3 px-5 py-3.5">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                    style.className,
                )}
            >
                <style.icon className="size-4" />
            </span>
            <div className="min-w-56 flex-1">
                <p
                    className={cn(
                        'font-medium',
                        done && 'line-through opacity-60',
                    )}
                >
                    {reminder.title}
                </p>
                <p className="text-xs text-muted-foreground">
                    {reminder.type_label} · {formatDate(reminder.due_on)}
                    {reminder.is_overdue && (
                        <span className="font-medium text-red-700 dark:text-red-400">
                            {' '}
                            · overdue
                        </span>
                    )}
                    {reminder.notes && ` · ${reminder.notes}`}
                </p>
            </div>
            <div className="flex items-center gap-1.5">
                {reminder.link && !done && (
                    <Button asChild size="sm" variant="outline">
                        <Link href={reminder.link} prefetch>
                            Open
                        </Link>
                    </Button>
                )}
                {!done && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="icon"
                                variant="outline"
                                aria-label="Snooze"
                            >
                                <AlarmClock />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {SNOOZE.map((option) => (
                                <DropdownMenuItem
                                    key={option.days}
                                    onSelect={() =>
                                        change({ snooze_days: option.days })
                                    }
                                >
                                    {option.label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
                <Button
                    size="icon"
                    variant={done ? 'ghost' : 'outline'}
                    aria-label={done ? 'Mark not done' : 'Mark done'}
                    onClick={() => change({ done: !done })}
                >
                    {done ? <Undo2 /> : <Check />}
                </Button>
            </div>
        </li>
    );
}
