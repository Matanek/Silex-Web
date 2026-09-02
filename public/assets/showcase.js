(() => {
    const gallery = document.querySelector('[data-showcase-gallery]');
    const dialog = document.querySelector('[data-showcase-lightbox]');

    if (!gallery || !dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const items = Array.from(gallery.querySelectorAll('[data-showcase-item]'));
    const image = dialog.querySelector('[data-showcase-image]');
    const title = dialog.querySelector('[data-showcase-title]');
    const description = dialog.querySelector('[data-showcase-description]');
    const count = dialog.querySelector('[data-showcase-count]');
    const close = dialog.querySelector('[data-showcase-close]');
    const previous = dialog.querySelector('[data-showcase-previous]');
    const next = dialog.querySelector('[data-showcase-next]');
    const countLabel = gallery.dataset.countLabel || '{current}/{total}';
    let activeIndex = 0;

    const render = (index) => {
        activeIndex = (index + items.length) % items.length;
        const item = items[activeIndex];

        image.src = item.href;
        image.alt = item.dataset.alt || '';
        title.textContent = item.dataset.title || '';
        description.textContent = item.dataset.description || '';
        count.textContent = countLabel
            .replace('{current}', String(activeIndex + 1).padStart(2, '0'))
            .replace('{total}', String(items.length).padStart(2, '0'));

        const nextImage = new Image();
        nextImage.src = items[(activeIndex + 1) % items.length].href;
    };

    const open = (index) => {
        render(index);
        dialog.showModal();
        document.body.classList.add('showcase-lightbox-open');
    };

    items.forEach((item, index) => {
        item.addEventListener('click', (event) => {
            event.preventDefault();
            open(index);
        });
    });

    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => render(activeIndex - 1));
    next.addEventListener('click', () => render(activeIndex + 1));

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            dialog.close();
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            render(activeIndex - 1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            render(activeIndex + 1);
        }
    });

    dialog.addEventListener('close', () => {
        document.body.classList.remove('showcase-lightbox-open');
    });
})();
