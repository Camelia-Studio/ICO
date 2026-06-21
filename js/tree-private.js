function generateShareLink(path, title, shareDownload = true, shareSource = true, shareShare = true) {
    document.getElementById('sharePath').value = path;
    document.getElementById('shareAlbumTitle').textContent = title;

    const dlCheck = document.getElementById('opt_download');
    if (dlCheck) dlCheck.checked = shareDownload === true || shareDownload === 'true';
    const srcCheck = document.getElementById('opt_source');
    if (srcCheck) srcCheck.checked = shareSource === true || shareSource === 'true';
    const shareCheck = document.getElementById('opt_share');
    if (shareCheck) shareCheck.checked = shareShare === true || shareShare === 'true';

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
    document.getElementById('moveFolderModal').style.display = 'none';
    document.getElementById('shareLinkModal').style.display = 'none';
}
