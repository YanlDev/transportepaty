import { useEffect } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
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
        <div className="relative flex min-h-svh flex-col bg-navy-900">
            <div className="relative z-10 flex flex-1 items-center justify-center p-6 md:p-10">
                <div className="w-full max-w-sm border-t-4 border-t-navy-800 bg-white px-8 py-12 shadow-2xl">
                    <div className="mb-8 flex flex-col items-center gap-2 text-center">
                        <div className="mb-1 grid size-14 place-items-center rounded-2xl bg-navy-800 text-white">
                            <AppLogoIcon className="size-8" />
                        </div>
                        <span className="text-3xl font-extrabold tracking-tight text-navy-800">
                            Transpaty
                        </span>
                        <span className="text-[11px] tracking-[0.22em] text-zinc-500 uppercase">
                            Transporte pesado
                        </span>
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
