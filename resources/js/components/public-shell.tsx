import type { ReactNode } from 'react';
import { Ticket } from 'lucide-react';

/** Centered card layout for the pages participants and gate staff see. */
export default function PublicShell({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-svh flex-col items-center bg-muted/40 px-4 py-10">
            <p className="mb-6 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Ticket className="size-4" /> EventPass
            </p>
            <main className="w-full max-w-md rounded-2xl border bg-background p-6 shadow-sm">
                {children}
            </main>
        </div>
    );
}
