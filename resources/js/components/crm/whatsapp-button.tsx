import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { whatsappLink } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Opens a WhatsApp chat (wa.me) with the number, on the phone or WhatsApp Web.
 */
export function WhatsAppButton({
    number,
    name,
    text,
    iconOnly = false,
    className,
}: {
    number: string;
    name?: string;
    text?: string;
    iconOnly?: boolean;
    className?: string;
}) {
    return (
        <Button
            asChild
            variant="outline"
            size={iconOnly ? 'icon' : 'sm'}
            className={cn(
                'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 hover:text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
                className,
            )}
        >
            <a
                href={whatsappLink(number, text)}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={name ? `WhatsApp ${name}` : 'WhatsApp'}
                onClick={(event) => event.stopPropagation()}
            >
                <MessageCircle />
                {!iconOnly && 'WhatsApp'}
            </a>
        </Button>
    );
}
