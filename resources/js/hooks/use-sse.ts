import { useEffect, useRef } from 'react';

/**
 * Subscribe to one named server-sent event with a native EventSource.
 *
 * The server closes each connection after a short window on purpose; the
 * browser's EventSource reconnects by itself, so this hook never closes on
 * error (unlike @laravel/stream-react's useEventStream, which would stop for
 * good). It also disconnects while the tab is hidden. Pass `null` as the url
 * to disconnect.
 */
export function useSse<T>(
    url: string | null,
    eventName: string,
    onData: (data: T) => void,
) {
    const handler = useRef(onData);

    useEffect(() => {
        handler.current = onData;
    }, [onData]);

    useEffect(() => {
        if (url === null) {
            return;
        }

        let source: EventSource | null = null;
        const listener = (event: MessageEvent<string>) =>
            handler.current(JSON.parse(event.data) as T);

        // A hidden tab holds no connection: each one ties up a PHP thread and
        // keeps the free instance awake. Reopening sends a fresh snapshot.
        const sync = () => {
            if (document.visibilityState === 'visible' && source === null) {
                source = new EventSource(url);
                source.addEventListener(eventName, listener);
            } else if (document.visibilityState === 'hidden' && source) {
                source.close();
                source = null;
            }
        };

        sync();
        document.addEventListener('visibilitychange', sync);

        return () => {
            document.removeEventListener('visibilitychange', sync);
            source?.close();
        };
    }, [url, eventName]);
}
