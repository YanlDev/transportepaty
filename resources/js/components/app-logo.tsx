import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="grid aspect-square size-9 shrink-0 place-items-center rounded-lg bg-navy-800 text-white">
                <AppLogoIcon className="size-5" />
            </div>
            {/* El texto se oculta cuando la barra lateral colapsa a solo iconos. */}
            <div className="ml-2 grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-semibold tracking-tight">
                    Transpaty
                </span>
                <span className="truncate text-[10px] tracking-[0.18em] text-muted-foreground uppercase">
                    Transporte pesado
                </span>
            </div>
        </>
    );
}
