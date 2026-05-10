function openAddModal() {
    document.getElementById('addUserModal').style.display = 'block';
}

function editUser(id, username) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_password').value = '';
    document.getElementById('editUserModal').style.display = 'block';
}

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
