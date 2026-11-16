import { useEffect, useRef } from 'react';

/**
 * Subscribe to one named server-sent event with a native EventSource.
 *
 * The server closes each connection after a short window on purpose; the
 * browser's EventSource reconnects by itself, so this hook never closes on
 * error (unlike @laravel/stream-react's useEventStream, which would stop for
 * good). Pass `null` as the url to disconnect.
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

        const source = new EventSource(url);
        const listener = (event: MessageEvent<string>) =>
            handler.current(JSON.parse(event.data) as T);

        source.addEventListener(eventName, listener);

        return () => source.close();
    }, [url, eventName]);
}
