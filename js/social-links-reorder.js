document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('socialLinksTable');
    if (!table || table.dataset.reorderable !== '1') return;

    const tbody = table.querySelector('tbody');
    let dragged = null;

    tbody.querySelectorAll('tr[data-id]').forEach(function (row) {
        row.setAttribute('draggable', 'true');

        row.addEventListener('dragstart', function () {
            dragged = row;
            row.classList.add('is-dragging');
        });

        row.addEventListener('dragend', function () {
            row.classList.remove('is-dragging');
            dragged = null;
            submitLinksOrder(tbody);
        });
    });

    tbody.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragged) return;

        const afterElement = getRowAfterElement(tbody, e.clientY);
        if (afterElement === null) {
            tbody.appendChild(dragged);
        } else {
            tbody.insertBefore(dragged, afterElement);
        }
    });
});

function getRowAfterElement(tbody, y) {
    const rows = Array.from(tbody.querySelectorAll('tr[data-id]:not(.is-dragging)'));

    return rows.reduce(function (closest, row) {
        const box = row.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: row };
        }

        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function submitLinksOrder(tbody) {
    const order = Array.from(tbody.querySelectorAll('tr[data-id]')).map(function (row) {
        return row.dataset.id;
    });

    const form = document.createElement('form');
    form.method = 'post';
    form.action = 'liens-sociaux.php?action=reorder';

    order.forEach(function (id) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order[]';
        input.value = id;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
