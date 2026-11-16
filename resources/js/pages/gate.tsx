import { Head, Link, router } from '@inertiajs/react';
import { Camera, CheckCircle2, XCircle } from 'lucide-react';
import QrScanner from 'qr-scanner';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import PublicShell from '@/components/public-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatTime } from '@/lib/dates';
import { index } from '@/routes/events';

const STORAGE_KEY = 'eventpass.gate';

type GateEvent = { name: string; venue: string; starts_at: string };
type Result =
    | { ok: true; name: string }
    | { ok: false; message: string; at?: string };

/** Take the device token from the link's #fragment once, then keep it locally. */
function takeToken(): string | null {
    const match = window.location.hash.match(/^#token=(.+)$/);

    try {
        if (match) {
            localStorage.setItem(STORAGE_KEY, decodeURIComponent(match[1]));
        }

        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return match ? decodeURIComponent(match[1]) : null;
    }
}

function api(token: string, path: string, body?: object) {
    return fetch(`/api/${path}`, {
        method: body ? 'POST' : 'GET',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
}

export default function Gate() {
    const [token] = useState(takeToken);
    const [event, setEvent] = useState<GateEvent | null>(null);
    const [invalid, setInvalid] = useState(token === null);
    const [result, setResult] = useState<Result | null>(null);
    const [busy, setBusy] = useState(false);
    const [cameraError, setCameraError] = useState(false);
    const [code, setCode] = useState('');
    const video = useRef<HTMLVideoElement>(null);

    useEffect(() => {
        // Drop the token from the address bar and Inertia's history entry.
        // Inertia writes that entry asynchronously and then fires 'navigate',
        // so clean up on that event too.
        const clean = () => {
            if (!window.location.hash.startsWith('#token=')) {
                return;
            }

            const state = history.state as { page?: object } | null;
            const url = window.location.pathname;
            history.replaceState(
                state?.page
                    ? { ...state, page: { ...state.page, url } }
                    : state,
                '',
                url,
            );
        };

        clean();

        return router.on('navigate', clean);
    }, []);

    useEffect(() => {
        if (token === null) {
            return;
        }

        void api(token, 'gate').then(async (response) => {
            if (response.ok) {
                setEvent(
                    ((await response.json()) as { event: GateEvent }).event,
                );
            } else {
                setInvalid(true);
            }
        });
    }, [token]);

    const checkIn = useCallback(
        async (value: string) => {
            if (token === null || value.trim() === '') {
                return;
            }

            setBusy(true);

            try {
                const response = await api(token, 'checkins', { code: value });
                const data = (await response.json()) as {
                    name?: string;
                    message?: string;
                    checked_in_at?: string;
                };

                if (response.status === 401) {
                    setInvalid(true);
                } else if (response.ok && data.name) {
                    setResult({ ok: true, name: data.name });
                } else {
                    setResult({
                        ok: false,
                        message:
                            response.status === 429
                                ? 'Muitas leituras seguidas, aguarde um instante'
                                : (data.message ?? 'Erro ao validar'),
                        at: data.checked_in_at,
                    });
                }
            } catch {
                setResult({ ok: false, message: 'Sem conexão com o servidor' });
            } finally {
                setBusy(false);
            }
        },
        [token],
    );

    useEffect(() => {
        if (event === null || video.current === null) {
            return;
        }

        const scanner = new QrScanner(
            video.current,
            (scan) => {
                // Pause so one QR held in front of the camera counts once.
                void scanner.pause();
                void checkIn(scan.data).finally(() =>
                    setTimeout(() => void scanner.start(), 2000),
                );
            },
            { preferredCamera: 'environment', highlightScanRegion: true },
        );

        scanner.start().catch(() => setCameraError(true));

        return () => scanner.destroy();
    }, [event, checkIn]);

    function submit(e: FormEvent) {
        e.preventDefault();
        void checkIn(code).then(() => setCode(''));
    }

    return (
        <PublicShell>
            <Head title="Portaria" />

            {invalid ? (
                <p className="rounded-lg bg-destructive/10 p-4 text-center font-medium text-destructive">
                    Dispositivo revogado ou inválido
                </p>
            ) : event === null ? (
                <p className="flex items-center justify-center gap-2">
                    <Spinner /> Conectando…
                </p>
            ) : (
                <div className="space-y-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Portaria
                        </p>
                        <h1 className="text-xl font-semibold">{event.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {formatDateTime(event.starts_at)} · {event.venue}
                        </p>
                    </div>

                    {result && (
                        <div
                            role="status"
                            className={`flex items-center gap-3 rounded-xl p-4 text-lg font-semibold text-white ${result.ok ? 'bg-green-600' : 'bg-red-600'}`}
                        >
                            {result.ok ? (
                                <CheckCircle2 className="size-8 shrink-0" />
                            ) : (
                                <XCircle className="size-8 shrink-0" />
                            )}
                            <span>
                                {result.ok
                                    ? `Entrada liberada — ${result.name}`
                                    : result.message}
                                {!result.ok && result.at && (
                                    <span className="block text-sm font-normal">
                                        às {formatTime(result.at)}
                                    </span>
                                )}
                            </span>
                        </div>
                    )}

                    {cameraError ? (
                        <p className="flex items-center gap-2 rounded-lg bg-muted p-3 text-sm">
                            <Camera className="size-4" /> Câmera indisponível.
                            Digite o código abaixo.
                        </p>
                    ) : (
                        <video
                            ref={video}
                            className="aspect-square w-full rounded-xl bg-black object-cover"
                            muted
                            playsInline
                        />
                    )}

                    <form onSubmit={submit} className="flex gap-2">
                        <Input
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            placeholder="Código do ingresso"
                            aria-label="Código do ingresso"
                            autoCapitalize="characters"
                            className="font-mono tracking-widest uppercase"
                        />
                        <Button disabled={busy || code.trim() === ''}>
                            {busy ? <Spinner /> : 'Validar'}
                        </Button>
                    </form>

                    <Link
                        href={index()}
                        className="block text-center text-sm text-muted-foreground underline"
                    >
                        Voltar ao painel
                    </Link>
                </div>
            )}
        </PublicShell>
    );
}
