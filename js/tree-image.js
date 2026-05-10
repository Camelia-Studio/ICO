function updateActionButtons() {
    const checkboxes         = document.querySelectorAll('.image-checkbox');
    const selectedCheckboxes = document.querySelectorAll('.image-checkbox:checked');
    const count = selectedCheckboxes.length;

    const deleteBtn    = document.getElementById('deleteSelectedBtn');
    const moveBtn      = document.getElementById('moveSelectedBtn');
    const selectAllBtn = document.getElementById('selectAllBtn');

    if (deleteBtn) deleteBtn.style.display = count > 0 ? 'inline-flex' : 'none';
    if (moveBtn)   moveBtn.style.display   = count > 0 ? 'inline-flex' : 'none';

    if (selectAllBtn) {
        selectAllBtn.textContent = checkboxes.length === selectedCheckboxes.length
            ? 'Tout désélectionner'
            : 'Tout sélectionner';
    }
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.image-checkbox');
    const allChecked = document.querySelectorAll('.image-checkbox:checked').length === checkboxes.length;
    checkboxes.forEach(cb => { cb.checked = !allChecked; });
    updateActionButtons();
}

function deleteImage(imageName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
        const form = document.getElementById('imagesForm');
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="images[]" value="${imageName}">
        `;
        form.submit();
    }
}

function deleteSelected() {
    const checkboxes = document.querySelectorAll('.image-checkbox:checked');
    if (checkboxes.length > 0 && confirm('Êtes-vous sûr de vouloir supprimer les images sélectionnées ?')) {
        document.getElementById('formAction').value = 'delete';
        document.getElementById('imagesForm').submit();
    }
}

function toggleTop(imageName) {
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_top">
        <input type="hidden" name="image" value="${imageName}">
    `;
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', function () {
    updateActionButtons();

    let lastChecked = null;

    document.querySelectorAll('.image-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('click', function (e) {
            if (e.shiftKey && lastChecked && lastChecked !== this) {
                const checkboxes = Array.from(document.querySelectorAll('.image-checkbox'));
                const currentIndex = checkboxes.indexOf(this);
                const lastIndex   = checkboxes.indexOf(lastChecked);
                const [start, end] = currentIndex < lastIndex
                    ? [currentIndex, lastIndex]
                    : [lastIndex, currentIndex];
                checkboxes.slice(start, end + 1).forEach(cb => { cb.checked = this.checked; });
            }
            lastChecked = this;
            updateActionButtons();
        });
    });

    const modal           = document.getElementById('uploadModal');
    const dropZone        = document.getElementById('dropZone');
    const uploadForm      = document.getElementById('uploadForm');
    const imageUploadForm = document.getElementById('imageUploadForm');

    if (uploadForm) {
        uploadForm.addEventListener('submit', function () {
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput && fileInput.files && fileInput.files.length > 0) {
                modal.style.display = 'block';
            }
        });
    }

    if (imageUploadForm) {
        imageUploadForm.addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                modal.style.display = 'block';
                uploadForm.submit();
            }
        });
    }

    if (dropZone) {
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('drag-over');
        });
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const dataTransfer = new DataTransfer();
                for (let file of files) { dataTransfer.items.add(file); }
                imageUploadForm.files = dataTransfer.files;
                modal.style.display = 'block';
                uploadForm.submit();
            }
        });
    }
});
