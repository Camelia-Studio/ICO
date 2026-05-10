function createSubfolder(path) {
    document.getElementById('parentPath').value = path;
    document.getElementById('createFolderModal').style.display = 'block';
}

function editFolder(path, title, description, matureContent, moreInfoUrl, hasImages) {
    document.getElementById('editPath').value = path;
    document.getElementById('edit_name').value = decodeURIComponent(title);
    document.getElementById('edit_description').value = decodeURIComponent(description);
    document.getElementById('edit_mature_content').checked = matureContent;
    document.getElementById('edit_more_info_url').value = decodeURIComponent(moreInfoUrl);
    const field = document.getElementById('edit_more_info_url_field');
    const show = hasImages === true || hasImages === 'true';
    if (field) {
        field.style.display = show ? 'block' : 'none';
        if (!show) document.getElementById('edit_more_info_url').value = '';
    }
    document.getElementById('editFolderModal').style.display = 'block';
}

function deleteFolder(path) {
    document.getElementById('deletePath').value = path;
    document.getElementById('deleteFolderModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('createFolderModal').style.display = 'none';
    document.getElementById('editFolderModal').style.display = 'none';
    document.getElementById('deleteFolderModal').style.display = 'none';
}

window.onclick = function (event) {
    if (event.target.classList.contains('modal')) closeModal();
};
