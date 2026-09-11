/**
 * La marca en la cabecera del sidebar y del header móvil.
 *
 * Son dos piezas distintas porque el sidebar tiene dos estados: expandido
 * muestra el logo horizontal completo, y colapsado —48px de ancho— solo entra
 * el isotipo. El cambio lo hace CSS con `group-data-[collapsible=icon]`, no
 * JavaScript, así no hay salto al montar.
 *
 * Cada pieza viene en dos tintas (camión azul para fondo claro, camión blanco
 * para fondo oscuro) porque el logo no es monocromo y no se puede recolorear
 * con `currentColor`. Las dos se montan y `dark:` oculta la que no va: el
 * navegador solo descarga la visible.
 */
export default function AppLogo() {
    return (
        <>
            <span className="hidden shrink-0 group-data-[collapsible=icon]:block">
                <Isotipo />
            </span>

            <span className="block min-w-0 group-data-[collapsible=icon]:hidden">
                <LogoHorizontal />
            </span>
        </>
    );
}

/** Solo el camión y el globo, para cuando el sidebar está colapsado. */
function Isotipo() {
    return (
        <>
            <img
                src="/marca/isotipo.png"
                alt="Transportes Paty"
                width={36}
                height={24}
                className="h-6 w-9 object-contain dark:hidden"
            />
            <img
                src="/marca/isotipo-oscuro.png"
                alt="Transportes Paty"
                width={36}
                height={24}
                className="hidden h-6 w-9 object-contain dark:block"
            />
        </>
    );
}

/** El logo completo con el wordmark, para el sidebar expandido. */
function LogoHorizontal() {
    return (
        <>
            <img
                src="/marca/logo-horizontal.png"
                alt="Empresa de Transportes Paty S.C.R.L."
                width={228}
                height={52}
                className="h-9 w-auto max-w-full object-contain object-left dark:hidden"
            />
            <img
                src="/marca/logo-horizontal-oscuro.png"
                alt="Empresa de Transportes Paty S.C.R.L."
                width={228}
                height={53}
                className="hidden h-9 w-auto max-w-full object-contain object-left dark:block"
            />
        </>
    );
}
