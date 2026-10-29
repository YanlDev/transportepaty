import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            esAmbientePrueba: boolean;
            /** Pendientes de hoy por entrada del menú (taller, abastecimiento, programacion). */
            pendientesMenu: Partial<
                Record<'taller' | 'abastecimiento' | 'programacion', number>
            >;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
