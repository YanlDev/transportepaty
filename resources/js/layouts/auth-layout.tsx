import { useEffect } from 'react';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({ title, children }: AuthLayoutProps) {
    useEffect(() => {
        const root = document.documentElement;
        const wasDark = root.classList.contains('dark');

        root.classList.remove('dark');
        root.style.colorScheme = 'light';

        return () => {
            if (wasDark) {
                root.classList.add('dark');
                root.style.colorScheme = 'dark';
            }
        };
    }, []);

    return (
        <div className="relative flex min-h-svh flex-col bg-marca-900">
            <div className="relative z-10 flex flex-1 items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm border-t-4 border-t-primary bg-white px-8 py-12 shadow-2xl">
                    <div className="mb-8 flex flex-col items-center gap-2 text-center">
                        {/* El logo real, el mismo archivo que muestra el
                            sidebar. La variante clara alcanza: la tarjeta es
                            blanca y el layout fuerza modo claro. */}
                        <img
                            src="/marca/logo-horizontal.png"
                            alt="Empresa de Transportes Paty S.C.R.L."
                            width={228}
                            height={52}
                            className="mb-3 h-12 w-auto max-w-full object-contain"
                        />
                        {title && (
                            <h2 className="text-xl font-semibold text-zinc-900">
                                {title}
                            </h2>
                        )}
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
