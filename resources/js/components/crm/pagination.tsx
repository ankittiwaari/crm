import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

export function Pagination<T>({ page }: { page: Paginated<T> }) {
    const { meta, links } = page;

    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
            <span>
                Showing {meta.from}–{meta.to} of {meta.total}
            </span>
            {meta.last_page > 1 && (
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!links.prev}
                        asChild={!!links.prev}
                    >
                        {links.prev ? (
                            <Link href={links.prev} preserveScroll>
                                <ChevronLeft />
                                Previous
                            </Link>
                        ) : (
                            <span>
                                <ChevronLeft />
                                Previous
                            </span>
                        )}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!links.next}
                        asChild={!!links.next}
                    >
                        {links.next ? (
                            <Link href={links.next} preserveScroll>
                                Next
                                <ChevronRight />
                            </Link>
                        ) : (
                            <span>
                                Next
                                <ChevronRight />
                            </span>
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
