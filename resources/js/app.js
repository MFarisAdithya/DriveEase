document.querySelectorAll('[data-account-menu]').forEach((menu) => {
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
});
