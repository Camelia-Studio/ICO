function acceptMatureContent() {
    document.body.classList.remove('gallery-page-mature');
    document.body.classList.remove('content-blurred');
    const warning = document.getElementById('mature-warning');
    if (warning) {
        warning.style.opacity = '0';
        setTimeout(() => { warning.style.display = 'none'; }, 300);
    }
}
