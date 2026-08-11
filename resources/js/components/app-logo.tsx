import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <div className="text-mspas-navy dark:text-mspas-cyan flex aspect-square size-9 items-center justify-center">
                <AppLogoIcon className="size-9" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-sm leading-tight font-semibold">Inventario de Bienes</span>
                <span className="text-muted-foreground truncate text-xs leading-tight">DDRISST · MSPAS</span>
            </div>
        </>
    );
}
