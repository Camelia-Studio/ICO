document.addEventListener('DOMContentLoaded', function () {
    const grid = document.querySelector('.images-grid[data-reorderable="1"]');
    if (!grid) return;

    let dragged = null;

    grid.querySelectorAll('.image-item').forEach(function (item) {
        item.setAttribute('draggable', 'true');

        item.addEventListener('dragstart', function () {
            dragged = item;
            item.classList.add('is-dragging');
        });

        item.addEventListener('dragend', function () {
            item.classList.remove('is-dragging');
            dragged = null;
            submitCarouselOrder(grid);
        });
    });

    grid.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragged) return;

        const afterElement = getDragAfterElement(grid, e.clientX, e.clientY);
        if (afterElement === null) {
            grid.appendChild(dragged);
        } else {
            grid.insertBefore(dragged, afterElement);
        }
    });
});

function getDragAfterElement(container, x, y) {
    const items = Array.from(container.querySelectorAll('.image-item:not(.is-dragging)'));

    return items.reduce(function (closest, child) {
        const box = child.getBoundingClientRect();
        const offsetX = x - box.left - box.width / 2;
        const offsetY = y - box.top - box.height / 2;
        const distance = Math.hypot(offsetX, offsetY);

        if (distance < closest.distance) {
            return { distance: distance, element: child };
        }

        return closest;
    }, { distance: Number.POSITIVE_INFINITY, element: null }).element;
}

function submitCarouselOrder(grid) {
    const order = Array.from(grid.querySelectorAll('.image-item')).map(function (item) {
        return item.dataset.name;
    });

    const form = document.createElement('form');
    form.method = 'post';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'reorder';
    form.appendChild(actionInput);

    order.forEach(function (name) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'order[]';
        input.value = name;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
