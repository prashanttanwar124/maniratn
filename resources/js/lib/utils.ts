export function toUrl(href: string): string {
    return href;
}

export function urlIsActive(href: string, currentPath: string): boolean {
    return currentPath.startsWith(href);
}
