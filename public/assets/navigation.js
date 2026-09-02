(() => {
    const toggle = document.querySelector('[data-navigation-toggle]');
    const panel = document.querySelector('[data-navigation-panel]');
    const backdrop = document.querySelector('[data-navigation-backdrop]');

    if (!toggle || !panel || !backdrop) {
        return;
    }

    const desktop = window.matchMedia('(min-width: 651px)');
    const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let isOpen = false;

    const centerCurrentDocument = (container) => {
        const current = container.querySelector('[data-current-document]');
        if (!current) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const currentRect = current.getBoundingClientRect();
        const centeredTop = container.scrollTop
            + currentRect.top
            - containerRect.top
            - ((container.clientHeight - currentRect.height) / 2);

        container.scrollTo({ top: Math.max(0, centeredTop), behavior: 'auto' });
    };

    const setOpen = (open, restoreFocus = false) => {
        isOpen = open;
        document.body.classList.toggle('navigation-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? toggle.dataset.closeLabel : toggle.dataset.openLabel);
        panel.setAttribute('aria-hidden', String(!open));

        if (open) {
            panel.removeAttribute('inert');
            window.requestAnimationFrame(() => {
                centerCurrentDocument(panel);
                const focusTarget = panel.querySelector('[data-current-document]')
                    ?? panel.querySelector(focusableSelector);
                focusTarget?.focus({ preventScroll: true });
            });
            return;
        }

        panel.setAttribute('inert', '');
        if (restoreFocus) {
            toggle.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(!isOpen, isOpen));
    backdrop.addEventListener('click', () => setOpen(false, true));
    panel.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!isOpen) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false, true);
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(panel.querySelectorAll(focusableSelector));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    });

    desktop.addEventListener('change', (event) => {
        if (event.matches && isOpen) {
            setOpen(false);
        }
    });

    document.querySelectorAll('.docs-sidebar').forEach(centerCurrentDocument);
})();
