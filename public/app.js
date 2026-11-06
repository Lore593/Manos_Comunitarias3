'use strict';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validate]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const password = form.querySelector('input[name="password"]');

            if (password && password.value.length < 8) {
                event.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
                password.focus();
            }
        });
    });
});


// Visor de fotos de donaciones: al pulsar una foto se abre en grande.
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalDonationImage');
    const modalCaption = document.getElementById('modalDonationCaption');

    if (!modal || !modalImage || !modalCaption) return;

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modalImage.src = '';
        document.body.classList.remove('modal-open');
    };

    document.querySelectorAll('.post-image-button').forEach((button) => {
        button.addEventListener('click', () => {
            modalImage.src = button.dataset.image;
            modalImage.alt = button.dataset.imageAlt || 'Foto de la donación';
            modalCaption.textContent = button.dataset.imageAlt || 'Foto de la donación';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('modal-open');
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
});
