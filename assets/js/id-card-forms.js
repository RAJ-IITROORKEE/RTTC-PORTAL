(function () {
    'use strict';

    function closest(element, selector) {
        if (!element) return null;
        if (element.closest) return element.closest(selector);
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) return element;
            element = element.parentElement;
        }
        return null;
    }

    function clearPreview(preview) {
        if (!preview) return;
        preview.removeAttribute('src');
        preview.classList.remove('is-visible');
        preview.setAttribute('aria-hidden', 'true');
    }

    function setPreview(preview, source) {
        if (!preview) return;
        preview.src = source;
        preview.classList.add('is-visible');
        preview.setAttribute('aria-hidden', 'false');
    }

    function normalisePhone(value) {
        var digits = String(value || '').replace(/\D/g, '');
        return digits.length === 12 && digits.slice(0, 2) === '91' ? digits.slice(2) : digits;
    }

    function validatePhone(input) {
        var number = normalisePhone(input.value);
        input.setCustomValidity(input.value && !/^[6-9][0-9]{9}$/.test(number) ? 'Enter a valid 10-digit Indian mobile number.' : '');
    }

    function validatePhoto(input, preview, maxPhotoSize) {
        var file = input.files && input.files[0];
        var allowedExtensions = /\.(jpe?g|png)$/i;
        var reader;
        var fileKey;

        input.setCustomValidity('');
        if (!file) {
            delete input.dataset.idCardPhotoKey;
            clearPreview(preview);
            return;
        }
        if ((file.type && file.type !== 'image/jpeg' && file.type !== 'image/png') || (!file.type && !allowedExtensions.test(file.name))) {
            input.setCustomValidity('Select a JPEG or PNG photo.');
            delete input.dataset.idCardPhotoKey;
            clearPreview(preview);
            return;
        }
        if (file.size > maxPhotoSize) {
            input.setCustomValidity('Photo file size must not exceed 2 MB.');
            delete input.dataset.idCardPhotoKey;
            clearPreview(preview);
            return;
        }
        fileKey = file.name + ':' + file.size + ':' + file.lastModified;
        if (input.dataset.idCardPhotoKey === fileKey) return;
        input.dataset.idCardPhotoKey = fileKey;
        reader = new FileReader();
        reader.onerror = function () {
            if (input.dataset.idCardPhotoKey !== fileKey) return;
            input.setCustomValidity('The selected photo could not be read. Please choose another image.');
            delete input.dataset.idCardPhotoKey;
            clearPreview(preview);
        };
        reader.onload = function (event) {
            if (input.dataset.idCardPhotoKey === fileKey) setPreview(preview, event.target.result);
        };
        reader.readAsDataURL(file);
    }

    function updateFieldState(field, reveal) {
        var valid;
        var feedback;
        if (!reveal && field.dataset.idCardTouched !== 'true') return;
        valid = field.checkValidity();
        field.classList.remove('is-valid', 'is-invalid');
        field.classList.add(valid ? 'is-valid' : 'is-invalid');
        if (valid) return;
        feedback = field.parentElement.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.insertAdjacentElement('afterend', feedback);
        }
        feedback.textContent = field.validationMessage || 'Complete this field before submitting.';
    }

    function updateSubmitState(form, reveal) {
        var phone = form.querySelector('#id-card-contact');
        var photo = form.querySelector('#id-card-photo');
        var preview = form.querySelector('#id-card-photo-preview');
        var fields = form.querySelectorAll('[required]');
        var submitWrap = form.querySelector('[data-id-card-submit-wrap]');
        var status = form.querySelector('[data-id-card-validation-status]');
        var maxPhotoSize = Number(form.dataset.idCardMaxPhotoSize || 2097152);
        var valid;

        if (phone) validatePhone(phone);
        if (photo) validatePhoto(photo, preview, maxPhotoSize);
        Array.prototype.forEach.call(fields, function (field) { updateFieldState(field, reveal); });
        valid = form.checkValidity();
        if (submitWrap) submitWrap.hidden = !valid;
        if (status) {
            status.hidden = !valid;
            status.className = valid ? 'alert alert-success mb-0' : '';
            status.textContent = valid ? 'All details are valid. You can now review and submit your application.' : '';
        }
        return valid;
    }

    function validateForm(form) {
        Array.prototype.forEach.call(form.querySelectorAll('[required]'), function (field) {
            field.dataset.idCardTouched = 'true';
        });
        form.classList.add('was-validated');
        if (updateSubmitState(form, true)) return true;
        var firstInvalid = form.querySelector(':invalid');
        if (firstInvalid && typeof firstInvalid.focus === 'function') firstInvalid.focus();
        return false;
    }

    function modalInstance(modal) {
        if (!window.bootstrap || !window.bootstrap.Modal) return null;
        return typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
            ? window.bootstrap.Modal.getOrCreateInstance(modal)
            : new window.bootstrap.Modal(modal);
    }

    function nativeSubmit(form, submitter) {
        form.dataset.idCardConfirmed = 'true';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
            return;
        }
        HTMLFormElement.prototype.submit.call(form);
    }

    function initialiseForm(form) {
        var modal = document.getElementById('idCardConfirmModal');
        var lastSubmitter = null;
        var fields = form.querySelectorAll('[required]');

        form.noValidate = true;
        Array.prototype.forEach.call(fields, function (field) {
            var eventName = field.type === 'file' || field.type === 'checkbox' || field.tagName === 'SELECT' ? 'change' : 'input';
            field.addEventListener(eventName, function () {
                field.dataset.idCardTouched = 'true';
                updateSubmitState(form, false);
            });
            field.addEventListener('blur', function () {
                field.dataset.idCardTouched = 'true';
                updateSubmitState(form, false);
            });
        });
        updateSubmitState(form, false);

        form.addEventListener('click', function (event) {
            var submitter = closest(event.target, 'button[type="submit"], input[type="submit"]');
            if (submitter && form.contains(submitter)) lastSubmitter = submitter;
        });
        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || lastSubmitter;
            if (form.dataset.idCardConfirmed === 'true') {
                delete form.dataset.idCardConfirmed;
                return;
            }
            if (!validateForm(form)) {
                event.preventDefault();
                return;
            }
            if (modal) {
                event.preventDefault();
                modal._idCardActiveForm = form;
                modal._idCardActiveSubmitter = submitter;
                var instance = modalInstance(modal);
                if (instance) instance.show();
                else if (window.confirm('Submit this ID card application?')) nativeSubmit(form, submitter);
            }
        });
        if (!modal || modal.dataset.idCardConfirmBound === 'true') return;
        modal.dataset.idCardConfirmBound = 'true';
        modal.addEventListener('click', function (event) {
            var button = closest(event.target, '[data-id-card-confirm-submit]');
            var activeForm = modal._idCardActiveForm;
            var activeSubmitter = modal._idCardActiveSubmitter;
            var instance;
            if (!button || !activeForm || !modal.contains(button) || !validateForm(activeForm)) return;
            instance = modalInstance(modal);
            if (instance) instance.hide();
            nativeSubmit(activeForm, activeSubmitter);
            modal._idCardActiveForm = null;
            modal._idCardActiveSubmitter = null;
        });
    }

    function initialise() {
        Array.prototype.forEach.call(document.querySelectorAll('form[data-id-card-form]'), initialiseForm);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
    else initialise();
})();
