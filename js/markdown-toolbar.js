function wrapSelection(textarea, prefix, suffix, placeholder) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const value = textarea.value;
    const selected = value.slice(start, end) || placeholder;

    textarea.value = value.slice(0, start) + prefix + selected + suffix + value.slice(end);

    const selStart = start + prefix.length;
    textarea.setSelectionRange(selStart, selStart + selected.length);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function prefixLines(textarea, prefix, numbered) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const value = textarea.value;

    const lineStart = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = value.indexOf('\n', end) === -1 ? value.length : value.indexOf('\n', end);

    const lines = value.slice(lineStart, lineEnd).split('\n');
    const newBlock = lines.map((line, i) => (numbered ? (i + 1) + '. ' : prefix) + line).join('\n');

    textarea.value = value.slice(0, lineStart) + newBlock + value.slice(lineEnd);
    textarea.setSelectionRange(lineStart, lineStart + newBlock.length);
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

const MARKDOWN_TOOLBAR_ACTIONS = {
    bold:    ta => wrapSelection(ta, '**', '**', 'texte en gras'),
    italic:  ta => wrapSelection(ta, '*', '*', 'texte en italique'),
    heading: ta => prefixLines(ta, '## '),
    link:    ta => wrapSelection(ta, '[', '](https://)', 'texte du lien'),
    ul:      ta => prefixLines(ta, '- '),
    ol:      ta => prefixLines(ta, '', true),
    quote:   ta => prefixLines(ta, '> '),
    code:    ta => wrapSelection(ta, '`', '`', 'code'),
};

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.markdown-toolbar').forEach(toolbar => {
        const textarea = document.getElementById(toolbar.dataset.target);
        if (!textarea) return;

        toolbar.querySelectorAll('[data-md-action]').forEach(button => {
            button.addEventListener('click', () => {
                const action = MARKDOWN_TOOLBAR_ACTIONS[button.dataset.mdAction];
                if (action) action(textarea);
            });
        });
    });
});
