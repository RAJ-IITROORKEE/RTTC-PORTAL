(function () {
    'use strict';

    var SHEET_WIDTH = 1600;
    var SHEET_HEIGHT = 1067;
    var CAPTURE_SCALE = 2;

    function closest(element, selector) {
        if (!element) return null;
        if (element.closest) return element.closest(selector);
        while (element && element.nodeType === 1) {
            if (element.matches && element.matches(selector)) return element;
            element = element.parentElement;
        }
        return null;
    }

    function createError(message) {
        var error = new Error(message);
        error.userMessage = message;
        return error;
    }

    function errorMessage(error, fallback) {
        return error && (error.userMessage || error.message) ? (error.userMessage || error.message) : fallback;
    }

    function getRoot(button) {
        return closest(button, '#id-card-export-root') || document.getElementById('id-card-export-root');
    }

    function showMessage(root, message, type) {
        var output = root.querySelector('[data-id-card-message]');
        if (!output) {
            output = document.createElement('div');
            output.setAttribute('data-id-card-message', '');
            output.setAttribute('role', 'alert');
            root.insertBefore(output, root.firstChild);
        }
        output.textContent = message;
        output.className = 'id-card-export-message alert alert-' + (type === 'success' ? 'success' : 'danger');
        output.hidden = false;
    }

    function clearMessage(root) {
        var output = root.querySelector('[data-id-card-message]');
        if (output) {
            output.hidden = true;
            output.textContent = '';
        }
    }

    function setLoading(button, label) {
        if (!button) return;
        if (!button.dataset.idCardOriginalHtml) button.dataset.idCardOriginalHtml = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + label;
    }

    function resetButton(button) {
        if (!button) return;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.idCardOriginalHtml) button.innerHTML = button.dataset.idCardOriginalHtml;
    }

    function normaliseStatus(value) {
        return String(value || '').replace(/^\s+|\s+$/g, '').toLowerCase();
    }

    function getStatus(root) {
        var badge = document.querySelector('[data-id-card-status]');
        return normaliseStatus(root.dataset.status || (badge && (badge.dataset.status || badge.textContent)));
    }

    function statusLabel(status) {
        return status === 'pending' ? 'Pending' : status === 'approved' ? 'Approved' : status === 'done' ? 'Done' : status;
    }

    function setStatus(root, status) {
        var badge = document.querySelector('[data-id-card-status]');
        root.dataset.status = status;
        if (badge) {
            badge.dataset.status = status;
            badge.textContent = statusLabel(status);
        }
    }

    function requestData(root, action) {
        var parameters = new URLSearchParams();
        if (!root.dataset.actionUrl || !root.dataset.applicationId) {
            throw createError('The ID card action details are missing. Please refresh the page and try again.');
        }
        if (!root.dataset.csrfToken) {
            throw createError('Your security token has expired. Please refresh the page and try again.');
        }
        parameters.set('action', action);
        parameters.set('application_id', root.dataset.applicationId);
        parameters.set(root.dataset.csrfField || 'csrf_token', root.dataset.csrfToken);
        return parameters.toString();
    }

    async function postAction(root, action) {
        var response;
        var raw;
        var payload;
        try {
            response = await window.fetch(root.dataset.actionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: requestData(root, action)
            });
            raw = await response.text();
        } catch (error) {
            throw createError('Unable to contact the server. Check your connection and try again.');
        }
        try {
            payload = raw ? JSON.parse(raw) : {};
        } catch (error) {
            throw createError('The server returned an unexpected response. Please refresh the page and try again.');
        }
        if (!response.ok || payload.success === false || payload.error) {
            throw createError(payload.message || 'The server could not complete the request. Please try again.');
        }
        return payload;
    }

    function responseValue(payload, names) {
        var containers = [payload];
        var keys = ['data', 'card', 'id_card', 'idCard', 'result'];
        var i;
        var j;
        for (i = 0; i < keys.length; i += 1) {
            if (payload && payload[keys[i]] && typeof payload[keys[i]] === 'object') containers.push(payload[keys[i]]);
        }
        for (i = 0; i < containers.length; i += 1) {
            for (j = 0; j < names.length; j += 1) {
                if (containers[i] && containers[i][names[j]] !== undefined && containers[i][names[j]] !== '') {
                    return String(containers[i][names[j]]);
                }
            }
        }
        return '';
    }

    function applyApprovedDates(root, payload) {
        var issue = responseValue(payload, ['issue_date', 'issueDate']);
        var validUntil = responseValue(payload, ['valid_until', 'validUntil']);
        if (!issue || !validUntil) {
            throw createError('The approval response did not include the issue and validity dates. Please try again.');
        }
        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-issue]'), function (element) {
            element.textContent = issue;
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-valid-until]'), function (element) {
            element.textContent = validUntil;
        });
    }

    function fitPreview(root) {
        var sheet = root.querySelector('#id-card-sheet');
        var stage = root.querySelector('.id-card-preview-stage');
        var scale;
        if (!sheet || !stage) return;
        scale = Math.min(stage.clientWidth / SHEET_WIDTH, 1);
        sheet.style.transform = 'scale(' + scale + ')';
        stage.style.height = Math.ceil(SHEET_HEIGHT * scale) + 'px';
        stage.style.minHeight = '0';
    }

    async function waitForAssets(sheet) {
        var waits = [];
        Array.prototype.forEach.call(sheet.querySelectorAll('img'), function (image) {
            if (image.complete) {
                if (image.naturalWidth > 0) return;
                waits.push(Promise.reject(createError('An ID card image could not be loaded.')));
                return;
            }
            waits.push(new Promise(function (resolve, reject) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', function () {
                    reject(createError('An ID card image could not be loaded.'));
                }, { once: true });
            }));
        });
        if (document.fonts && document.fonts.ready) waits.push(document.fonts.ready);
        await Promise.all(waits);
    }

    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) resolve(blob);
                else reject(createError('The PNG image could not be created. Please try again.'));
            }, 'image/png');
        });
    }

    async function captureSheet(sheet) {
        var previousTransform = sheet.style.transform;
        await waitForAssets(sheet);
        sheet.classList.add('id-card-capturing');
        sheet.style.transform = 'none';
        try {
            var canvas = await window.html2canvas(sheet, {
                scale: CAPTURE_SCALE,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                logging: false,
                width: SHEET_WIDTH,
                height: SHEET_HEIGHT,
                windowWidth: SHEET_WIDTH,
                windowHeight: SHEET_HEIGHT,
                scrollX: 0,
                scrollY: 0
            });
            return await canvasToBlob(canvas);
        } finally {
            sheet.style.transform = previousTransform;
            sheet.classList.remove('id-card-capturing');
        }
    }

    function triggerDownload(blob, filename) {
        var link = document.createElement('a');
        var objectUrl = window.URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = filename;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(function () { window.URL.revokeObjectURL(objectUrl); }, 1000);
    }

    async function exportCard(button, root) {
        var status = getStatus(root);
        var sheet = root.querySelector('#id-card-sheet');
        var reference = root.dataset.reference;
        var pngBlob;

        if (typeof window.html2canvas !== 'function') {
            showMessage(root, 'PNG export is still loading. Please wait a moment and try again.', 'error');
            return;
        }
        if (!sheet || !reference) {
            showMessage(root, 'The complete ID card preview is unavailable. Please refresh the page.', 'error');
            return;
        }
        if (status !== 'pending' && status !== 'approved' && status !== 'done') {
            showMessage(root, 'This ID card is not ready to download.', 'error');
            return;
        }

        clearMessage(root);
        setLoading(button, status === 'pending' ? 'Approving...' : 'Preparing PNG...');
        try {
            if (status === 'pending') {
                var approval = await postAction(root, 'approve');
                applyApprovedDates(root, approval);
                setStatus(root, 'approved');
            }

            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Creating high-quality PNG...';
            pngBlob = await captureSheet(sheet);
            triggerDownload(pngBlob, 'RTTC_ID_CARD_' + reference + '.png');

            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Finalising...';
            await postAction(root, 'mark_done');
            setStatus(root, 'done');
            showMessage(root, 'The high-quality ID card PNG has been downloaded successfully.', 'success');
        } catch (error) {
            showMessage(root, errorMessage(error, 'The ID card PNG could not be generated. Please try again.'), 'error');
        } finally {
            resetButton(button);
            fitPreview(root);
        }
    }

    async function deleteCard(button, root) {
        var holder = root.dataset.holderName || 'this applicant';
        var reference = root.dataset.reference || 'this ID card';
        if (!window.confirm('Delete pending application ' + reference + ' for ' + holder + '? This action cannot be undone.')) return;
        clearMessage(root);
        setLoading(button, 'Deleting...');
        try {
            await postAction(root, 'delete');
            window.location.reload();
        } catch (error) {
            showMessage(root, errorMessage(error, 'The ID card could not be deleted. Please try again.'), 'error');
            resetButton(button);
        }
    }

    function initialise() {
        var root = document.getElementById('id-card-export-root');
        if (!root) return;
        fitPreview(root);
        window.addEventListener('resize', function () { fitPreview(root); });
        if (window.ResizeObserver) {
            new window.ResizeObserver(function () { fitPreview(root); }).observe(root);
        }
        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-export]'), function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                exportCard(button, getRoot(button));
            });
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-delete]'), function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                deleteCard(button, getRoot(button));
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise);
    else initialise();
})();
