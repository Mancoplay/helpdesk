function resetPasswordToggleIcon(button, showPassword) {
    const icon = button.querySelector('i');

    button.setAttribute('aria-label', showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena');

    if (icon) {
        icon.classList.toggle('fa-eye', !showPassword);
        icon.classList.toggle('fa-eye-slash', showPassword);
    }
}

function setupPasswordToggles(root = document) {
    root.querySelectorAll('.js-password-toggle').forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }

        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const inputGroup = button.closest('.input-group');
            const input = inputGroup ? inputGroup.querySelector('input') : null;

            if (!input) {
                return;
            }

            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            resetPasswordToggleIcon(button, showPassword);
        });
    });
}

window.setupPasswordToggles = setupPasswordToggles;

document.addEventListener('DOMContentLoaded', () => {
    setupPasswordToggles();
});

document.addEventListener('shown.bs.modal', (event) => {
    setupPasswordToggles(event.target);
});
