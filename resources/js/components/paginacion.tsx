import { cn } from '@/lib/utils';
import { type Paginado } from '@/types';
import { Link } from '@inertiajs/react';

export function Paginacion<T>({ pagina }: { pagina: Paginado<T> }) {
    if (pagina.links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 px-1 pt-4">
            <p className="text-muted-foreground text-sm">
                Mostrando {pagina.from ?? 0}–{pagina.to ?? 0} de {pagina.total}
            </p>

            <div className="flex flex-wrap gap-1">
                {pagina.links.map((link, indice) =>
                    link.url ? (
                        <Link
                            key={indice}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                link.active
                                    ? 'border-mspas-navy bg-mspas-navy text-white dark:border-mspas-cyan dark:bg-mspas-cyan dark:text-neutral-900'
                                    : 'border-border text-muted-foreground hover:bg-accent',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={indice}
                            className="border-border text-muted-foreground/40 rounded-md border px-3 py-1.5 text-sm"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </div>
    );
}
