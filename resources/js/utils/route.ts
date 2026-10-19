/**
 * Helper to safely call route() with type safety
 */
export function routeIs(name: string | undefined, pattern?: string): boolean {
    if (!name) return false;
    try {
        const current = route().current() ?? '';
        if (pattern) {
            return current.includes(pattern);
        }
        return current === name;
    } catch {
        return false;
    }
}

/**
 * Get route URL safely
 */
export function routeUrl(name: string | undefined): string {
    if (!name) return '#';
    try {
        return route(name);
    } catch {
        return '#';
    }
}
