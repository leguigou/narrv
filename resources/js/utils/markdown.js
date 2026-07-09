import DOMPurify from 'dompurify';
import { marked } from 'marked';

marked.setOptions({
    breaks: true,
    gfm: true,
});

export function renderMarkdown(text) {
    if (!text) return '';

    return DOMPurify.sanitize(marked.parse(text), {
        USE_PROFILES: { html: true },
    });
}
