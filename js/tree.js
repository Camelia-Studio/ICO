function createSubfolder(path) {
    document.getElementById('parentPath').value = path;
    document.getElementById('createFolderModal').style.display = 'block';
}

function editFolder(path, title, description, matureContent, moreInfoUrl, hasImages, zipDownload = false) {
    document.getElementById('editPath').value = path;
    document.getElementById('edit_name').value = decodeURIComponent(title);
    document.getElementById('edit_description').value = decodeURIComponent(description);
    document.getElementById('edit_mature_content').checked = matureContent;

    const show = hasImages === true || hasImages === 'true';

    const field = document.getElementById('edit_more_info_url_field');
    if (field) {
        field.style.display = show ? 'block' : 'none';
    }

    const zipField = document.getElementById('edit_zip_download_field');
    if (zipField) {
        zipField.style.display = show ? 'block' : 'none';
    }

    const zipCheck = document.getElementById('edit_zip_download');
    if (zipCheck) {
        zipCheck.checked = zipDownload === true || zipDownload === 'true';
    }

    const decoded = decodeURIComponent(moreInfoUrl);
    const isInternalPage = decoded.startsWith('page.php?slug=');
    const radioPage     = document.getElementById('edit_link_type_page');
    const radioExternal = document.getElementById('edit_link_type_external');
    const pageSelect    = document.getElementById('edit_page_select');
    const urlInput      = document.getElementById('edit_more_info_url');

    if (isInternalPage && radioPage) {
        radioPage.checked = true;
        if (pageSelect) pageSelect.value = decoded;
        if (urlInput) urlInput.value = '';
    } else {
        if (radioExternal) radioExternal.checked = true;
        if (urlInput) urlInput.value = show ? decoded : '';
        if (pageSelect) pageSelect.value = '';
    }
    toggleLinkType('edit');

    document.getElementById('editFolderModal').style.display = 'block';
}

function toggleLinkType(prefix) {
    const radio = document.querySelector('input[name="' + prefix + '_link_type"]:checked');
    const extDiv  = document.getElementById(prefix + '_link_external');
    const pageDiv = document.getElementById(prefix + '_link_page');
    if (!radio || !extDiv) return;

    const isPage = radio.value === 'page';
    extDiv.style.display  = isPage ? 'none' : 'block';
    if (pageDiv) pageDiv.style.display = isPage ? 'block' : 'none';

    // Désactiver le champ caché pour qu'il ne soumette pas de valeur vide
    const urlInput   = document.getElementById(prefix === 'edit' ? 'edit_more_info_url' : 'more_info_url');
    const pageSelect = document.getElementById(prefix + '_page_select');
    if (urlInput)   urlInput.disabled   = isPage;
    if (pageSelect) pageSelect.disabled = !isPage;
}

function deleteFolder(path) {
    document.getElementById('deletePath').value = path;
    const folderName = decodeURIComponent(path.split('/').filter(p => p).pop() || path);
    const nameEl = document.getElementById('deleteFolderName');
    if (nameEl) nameEl.textContent = folderName;
    document.getElementById('deleteFolderModal').style.display = 'block';
}

function moveFolder(path, encodedTitle) {
    document.getElementById('movePath').value = path;
    document.getElementById('moveFolderName').textContent = decodeURIComponent(encodedTitle);

    const select = document.getElementById('move_dest');
    select.innerHTML = '';

    const parentPath = path.substring(0, path.lastIndexOf('/'));
    const folders = typeof allFolders !== 'undefined' ? allFolders : [];

    folders.forEach(f => {
        if (f.path === path || f.path.startsWith(path + '/') || f.path === parentPath) return;
        const opt = document.createElement('option');
        opt.value = f.path;
        opt.textContent = '  '.repeat(f.depth) + f.title;
        select.appendChild(opt);
    });

    document.getElementById('moveFolderModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('createFolderModal').style.display = 'none';
    document.getElementById('editFolderModal').style.display = 'none';
    document.getElementById('deleteFolderModal').style.display = 'none';
    document.getElementById('moveFolderModal').style.display = 'none';
}

window.onclick = function (event) {
    if (event.target.classList.contains('modal')) closeModal();
};
