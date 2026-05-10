function cleanExpiredKeys() {
    if (confirm('Voulez-vous supprimer toutes les clés expirées ?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = '<input type="hidden" name="action" value="clean_expired">';
        document.body.appendChild(form);
        form.submit();
    }
}

function copyShareUrl(button) {
    const input = button.previousElementSibling;
    input.select();
    document.execCommand('copy');

    const originalText = button.innerHTML;
    button.innerHTML = '✓';
    button.classList.add('copied');

    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('copied');
    }, 2000);
}

function updateFilters() {
    const statusFilter = document.getElementById('status-filter').value;
    const albumFilter = document.getElementById('album-filter').value;

    let url = 'clefs.php?filter=' + statusFilter;
    if (albumFilter) {
        url += '&album=' + albumFilter;
    }

    window.location.href = url;
}
