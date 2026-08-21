(function () {
    'use strict';

    function closest(element, selector) {
        if (!element) {
            return null;
        }
        if (element.closest) {
            return element.closest(selector);
        }
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    function setPreview(preview, source) {
        var image;

        if (!preview) {
            return;
        }

        if (preview.tagName === 'IMG') {
            preview.src = source;
        } else {
            image = preview.querySelector('img');
            if (!image) {
                image = document.createElement('img');
                image.alt = 'Selected ID card photo preview';
                preview.appendChild(image);
            }
            image.src = source;
        }

        preview.classList.add('is-visible');
        preview.setAttribute('aria-hidden', 'false');
    }

    function clearPreview(preview) {
        var image;

        if (!preview) {
            return;
        }

        if (preview.tagName === 'IMG') {
            preview.removeAttribute('src');
        } else {
            image = preview.querySelector('img');
            if (image) {
                image.removeAttribute('src');
            }
        }

        preview.classList.remove('is-visible');
        preview.setAttribute('aria-hidden', 'true');
    }

    function initialisePhotoPreview() {
        var input = document.getElementById('id-card-photo');
        var preview = document.getElementById('id-card-photo-preview');

        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            var reader;

            input.setCustomValidity('');

            if (!file) {
                clearPreview(preview);
                return;
            }

            if (!file.type || file.type.indexOf('image/') !== 0) {
                input.setCustomValidity('Please select a valid image file for the ID card photo.');
                clearPreview(preview);
                return;
            }

            reader = new FileReader();
            reader.onerror = function () {
                input.setCustomValidity('The selected photo could not be read. Please choose another image.');
                clearPreview(preview);
            };
            reader.onload = function (event) {
                setPreview(preview, event.target.result);
            };
            reader.readAsDataURL(file);
        });
    }

    function validateForm(form) {
        var valid = form.checkValidity();
        var firstInvalid;

        form.classList.add('was-validated');
        if (valid) {
            return true;
        }

        firstInvalid = form.querySelector(':invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
        }
        return false;
    }

    function modalInstance(modal) {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
            return window.bootstrap.Modal.getOrCreateInstance(modal);
        }
        return new window.bootstrap.Modal(modal);
    }

    function nativeSubmit(form, submitter) {
        var canBeSubmitter = submitter && (
            (submitter.tagName === 'BUTTON' && (submitter.type || 'submit').toLowerCase() === 'submit') ||
            (submitter.tagName === 'INPUT' && /^(submit|image)$/i.test(submitter.type || ''))
        );

        form.dataset.idCardConfirmed = 'true';

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(canBeSubmitter ? submitter : undefined);
            return;
        }

        HTMLFormElement.prototype.submit.call(form);
    }

    function initialiseForm(form) {
        var modal = document.getElementById('idCardConfirmModal');
        var lastSubmitter = null;
        var confirmSelector = '[data-id-card-confirm-submit], [data-id-card-confirm-action], #idCardConfirmSubmit, [data-confirm-submit], .btn-primary[type="button"], .btn-primary[type="submit"]';

        // Keep normal POST behaviour without JavaScript, but use Bootstrap feedback when it is available.
        form.noValidate = true;

        function requestConfirmation(submitter) {
            var instance;

            modal._idCardActiveForm = form;
            modal._idCardActiveSubmitter = submitter;
            instance = modalInstance(modal);

            if (instance) {
                instance.show();
                return;
            }

            if (window.confirm('Submit this ID card form?')) {
                nativeSubmit(form, submitter);
            }
        }

        form.addEventListener('click', function (event) {
            var submitter = closest(event.target, 'button[type="submit"], input[type="submit"]');

            if (submitter && form.contains(submitter)) {
                lastSubmitter = submitter;
            }
        });

        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || lastSubmitter;
            var confirmationButton = form.querySelector('[data-id-card-confirm]');

            if (form.dataset.idCardConfirmed === 'true') {
                delete form.dataset.idCardConfirmed;
                return;
            }

            if (!validateForm(form)) {
                event.preventDefault();
                return;
            }

            if (modal && ((submitter && submitter.hasAttribute('data-id-card-confirm')) || (!submitter && confirmationButton))) {
                event.preventDefault();
                requestConfirmation(submitter || confirmationButton);
            }
        });

        Array.prototype.forEach.call(form.querySelectorAll('[data-id-card-confirm]'), function (button) {
            if ((button.type || '').toLowerCase() === 'submit') {
                return;
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                if (validateForm(form)) {
                    if (modal) {
                        requestConfirmation(button);
                    } else {
                        nativeSubmit(form, button);
                    }
                }
            });
        });

        if (!modal || modal.dataset.idCardConfirmBound === 'true') {
            return;
        }

        modal.dataset.idCardConfirmBound = 'true';
        modal.addEventListener('click', function (event) {
            var button = closest(event.target, confirmSelector);
            var instance;
            var activeForm = modal._idCardActiveForm;
            var activeSubmitter = modal._idCardActiveSubmitter;

            if (!button || !modal.contains(button) || !activeForm) {
                return;
            }

            event.preventDefault();
            if (!validateForm(activeForm)) {
                return;
            }

            instance = modalInstance(modal);
            if (instance) {
                instance.hide();
            }
            nativeSubmit(activeForm, activeSubmitter);
            modal._idCardActiveForm = null;
            modal._idCardActiveSubmitter = null;
        });
    }

    function initialise() {
        initialisePhotoPreview();
        Array.prototype.forEach.call(document.querySelectorAll('form[data-id-card-form]'), initialiseForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
