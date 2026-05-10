document.addEventListener('DOMContentLoaded', function () {
    const backBtn = document.querySelector('.back-button');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            const referrer = document.referrer;
            if (referrer.includes('galeries.php') || referrer.includes('galeries-privees.php')) {
                window.close();
            } else {
                window.location.href = 'index.php';
            }
        });
    }
});

function copyToClipboard(text, button) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);

    const originalHTML = button.innerHTML;
    button.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 6L9 17l-5-5"></path>
        </svg>
        Copié !
    `;
    setTimeout(() => { button.innerHTML = originalHTML; }, 2000);
}

function shareImage() {
    copyToClipboard(window.location.href, document.querySelector('.action-button'));
}

function embedImage() {
    const img = document.querySelector('.share-image img');
    copyToClipboard(img.dataset.imageUrl, document.querySelector('.action-button:nth-child(2)'));
}
