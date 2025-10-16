// @ts-ignore
import React, { useEffect } from 'react';

/** Sync Docusaurus dark theme with custom `.darkness` on <body>. */
export default function Root({ children }: { children: React.ReactNode }) {
    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const apply = () => {
            const isDark = html.getAttribute('data-theme') === 'dark';
            body.classList.toggle('darkness', isDark);
        };
        const mo = new MutationObserver(apply);
        mo.observe(html, { attributes: true, attributeFilter: ['data-theme'] });
        apply();
        return () => mo.disconnect();
    }, []);
    // @ts-ignore
    return <>{children}</>;
}