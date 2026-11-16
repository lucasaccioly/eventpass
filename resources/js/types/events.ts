export type EventItem = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    venue: string;
    starts_at: string;
    capacity: number;
    registrations_count?: number;
};

export type EventStats = {
    registered: number;
    checked_in: number;
    capacity: number;
    latest: { name: string; checked_in_at: string }[];
};
