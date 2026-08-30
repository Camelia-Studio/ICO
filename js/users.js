function openAddModal() {
    updateAlbumAccessVisibility(document.getElementById('role'));
    document.getElementById('addUserModal').style.display = 'block';
}

function editUser(button) {
    document.getElementById('edit_user_id').value = button.dataset.userId;
    document.getElementById('edit_username').value = button.dataset.username;
    document.getElementById('edit_password').value = '';
    const roleSelect = document.getElementById('edit_role');
    roleSelect.value = button.dataset.role;
    roleSelect.disabled = button.dataset.isMain === '1';

    const selectedAlbums = JSON.parse(button.dataset.privateAlbums || '[]');
    document.querySelectorAll('#edit-private-albums input[type="checkbox"]').forEach((checkbox) => {
        checkbox.checked = selectedAlbums.includes(checkbox.value);
    });
    updateAlbumAccessVisibility(roleSelect);
    document.getElementById('editUserModal').style.display = 'block';
}

function updateAlbumAccessVisibility(select) {
    const fieldset = document.getElementById(select.dataset.albumTarget);
    fieldset.hidden = select.value !== 'visitor';
}

document.querySelectorAll('[data-role-select]').forEach((select) => {
    select.addEventListener('change', () => updateAlbumAccessVisibility(select));
});

function deleteUser(id) {
    document.getElementById('delete_user_id').value = id;
    document.getElementById('deleteUserModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};
