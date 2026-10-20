import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name, company } = usePage().props;

    return (
        <>
            {company?.logo_url ? (
                <img
                    src={company.logo_url}
                    alt=""
                    className="size-8 shrink-0 rounded-lg object-contain"
                />
            ) : (
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                    <AppLogoIcon className="size-5 fill-current text-white" />
                </div>
            )}
            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">
                    {company?.name ?? 'Your company'}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
