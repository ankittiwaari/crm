import type { Auth } from '@/types/auth';
import type { SharedCompany } from '@/types/company';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            company: SharedCompany | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
