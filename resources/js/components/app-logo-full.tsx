import LogoAccentBracket from '@/components/logo-accent-bracket';
import { cn } from '@/lib/utils';

/**
 * Wordmark completo de Transportes Paty («EMPRESA DE» / «TRANSPORTES PATY» /
 * doble trazo en L + «S.C.R.L.»), a los colores reales de marca (azul +
 * grafito). Pensado para fondos claros fijos — hoy solo la pantalla de login,
 * que fuerza modo claro (`auth-layout.tsx`) — por eso usa `--color-graphite`
 * directo en vez del `--foreground` que sí invierte en modo oscuro.
 */
export default function AppLogoFull({ className }: { className?: string }) {
    return (
        <div className={cn('flex flex-col items-start', className)}>
            <span className="text-xs font-bold tracking-[0.2em] text-[var(--color-graphite)] uppercase">
                Empresa de
            </span>
            <span className="text-4xl leading-[0.95] font-black tracking-tight text-primary uppercase">
                Transportes
                <br />
                Paty
            </span>
            <div className="mt-1.5 flex items-center gap-2">
                <LogoAccentBracket className="h-3.5 w-6 text-[var(--color-graphite)]" />
                <span className="text-xs font-semibold tracking-[0.15em] text-[var(--color-graphite)] uppercase">
                    S.C.R.L.
                </span>
            </div>
        </div>
    );
}
