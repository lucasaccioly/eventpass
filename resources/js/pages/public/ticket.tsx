import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import TicketStreamController from '@/actions/App/Http/Controllers/TicketStreamController';
import PublicShell from '@/components/public-shell';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useSse } from '@/hooks/use-sse';
import { formatDateTime } from '@/lib/dates';

export type TicketStatus = 'pending' | 'ready' | 'failed';

export type TicketProps = {
    public_id: string;
    name: string;
    code: string;
    status: TicketStatus;
    pdf_url: string | null;
    event: { name: string; slug: string; venue: string; starts_at: string };
};

type StatusEvent = { status: TicketStatus; pdf_url: string | null };

export default function TicketPage({ ticket }: { ticket: TicketProps }) {
    const [live, setLive] = useState<StatusEvent>({
        status: ticket.status,
        pdf_url: ticket.pdf_url,
    });

    // Listen only while the PDF is being generated.
    useSse<StatusEvent>(
        live.status === 'pending'
            ? TicketStreamController.url(ticket.public_id)
            : null,
        'status',
        setLive,
    );

    return (
        <PublicShell>
            <Head title={`Ingresso · ${ticket.event.name}`} />
            <p className="text-sm text-muted-foreground">Ingresso de</p>
            <h1 className="text-2xl font-semibold tracking-tight">
                {ticket.name}
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
                {ticket.event.name} · {formatDateTime(ticket.event.starts_at)} ·{' '}
                {ticket.event.venue}
            </p>

            <div className="my-6 rounded-xl border border-dashed p-4 text-center">
                <p className="text-xs tracking-widest text-muted-foreground uppercase">
                    Código
                </p>
                <p className="font-mono text-2xl font-semibold tracking-[0.2em]">
                    {ticket.code}
                </p>
            </div>

            <div aria-live="polite">
                {live.status === 'pending' && (
                    <p className="flex items-center justify-center gap-2 text-sm">
                        <Spinner /> Gerando seu ingresso…
                    </p>
                )}
                {live.status === 'ready' && live.pdf_url && (
                    <Button asChild className="w-full" size="lg">
                        <a href={live.pdf_url}>
                            <Download /> Baixar ingresso (PDF)
                        </a>
                    </Button>
                )}
                {live.status === 'failed' && (
                    <p className="rounded-lg bg-destructive/10 p-3 text-center text-sm text-destructive">
                        Não foi possível gerar. Recarregue a página.
                    </p>
                )}
            </div>

            <p className="mt-6 text-xs text-muted-foreground">
                Guarde este link: é o único acesso ao seu ingresso.
            </p>
        </PublicShell>
    );
}
