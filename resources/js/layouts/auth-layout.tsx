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
        <div
            className="relative flex min-h-svh flex-col bg-zinc-900 bg-cover bg-center"
            style={{ backgroundImage: 'url(/Portada_Login.jpg)' }}
        >
            <div className="relative z-10 flex flex-1 items-center justify-center p-6 md:justify-end md:p-10 lg:pr-24">
                <div className="w-full max-w-sm rounded-2xl border border-zinc-200 bg-white px-8 py-12 shadow-2xl ring-1 ring-black/5">
                    <div className="mb-8 flex flex-col items-center gap-2 text-center">
                        <span className="text-3xl font-extrabold tracking-tight text-emerald-800">
                            Selcosi
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
