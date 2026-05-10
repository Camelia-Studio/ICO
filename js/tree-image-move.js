function moveSelected() {
    const checkboxes = document.querySelectorAll('.image-checkbox:checked');
    if (checkboxes.length === 0) return;

    const container = document.getElementById('selected-images-container');
    container.innerHTML = '';

    checkboxes.forEach(checkbox => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'images[]';
        input.value = checkbox.value;
        container.appendChild(input);
    });

    document.getElementById('moveFolderModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};
