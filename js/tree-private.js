function generateShareLink(path, title) {
    document.getElementById('sharePath').value = path;
    document.getElementById('shareAlbumTitle').textContent = title;
    document.getElementById('shareLinkModal').style.display = 'block';
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

function closeModal() {
    document.getElementById('createFolderModal').style.display = 'none';
    document.getElementById('editFolderModal').style.display = 'none';
    document.getElementById('deleteFolderModal').style.display = 'none';
    document.getElementById('shareLinkModal').style.display = 'none';
}
