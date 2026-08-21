(function () {
    'use strict';

    var CARD_WIDTH = 638;
    var CARD_HEIGHT = 1011;
    var CARD_WIDTH_MM = 53.98;
    var CARD_HEIGHT_MM = 85.60;

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

    function createError(message) {
        var error = new Error(message);
        error.userMessage = message;
        return error;
    }

    function errorMessage(error, fallback) {
        if (error && error.userMessage) {
            return error.userMessage;
        }
        if (error && error.message) {
            return error.message;
        }
        return fallback;
    }

    function showMessage(root, message, type) {
        var output = root.querySelector('[data-id-card-message]');

        if (!output) {
            output = document.createElement('div');
            output.className = 'id-card-export-message';
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
        if (!button) {
            return;
        }
        if (!button.dataset.idCardOriginalHtml) {
            button.dataset.idCardOriginalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + label;
    }

    function resetButton(button) {
        if (!button) {
            return;
        }
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.idCardOriginalHtml) {
            button.innerHTML = button.dataset.idCardOriginalHtml;
        }
    }

    function librariesAvailable() {
        return typeof window.html2canvas === 'function' &&
            window.jspdf && typeof window.jspdf.jsPDF === 'function' &&
            typeof window.JSZip === 'function';
    }

    function getRoot(button) {
        return closest(button, '#id-card-export-root') || document.getElementById('id-card-export-root');
    }

    function normaliseStatus(value) {
        return String(value || '').replace(/^\s+|\s+$/g, '').toLowerCase();
    }

    function getStatus(root) {
        var badge = root.querySelector('[data-id-card-status]');
        return normaliseStatus(root.dataset.status || (badge && (badge.dataset.status || badge.textContent)));
    }

    function statusLabel(status) {
        if (status === 'pending') {
            return 'Pending';
        }
        if (status === 'approved') {
            return 'Approved';
        }
        if (status === 'done') {
            return 'Done';
        }
        return status;
    }

    function setStatus(root, status) {
        var badge = root.querySelector('[data-id-card-status]');
        root.dataset.status = status;
        if (badge) {
            badge.dataset.status = status;
            badge.textContent = statusLabel(status);
        }
    }

    function appendParameters(root, action) {
        var actionUrl = root.dataset.actionUrl;
        var applicationId = root.dataset.applicationId;
        var csrfToken = root.dataset.csrfToken;
        var csrfField = root.dataset.csrfField || 'csrf_token';
        var parameters = new URLSearchParams();

        if (!actionUrl) {
            throw createError('The ID card action URL is missing. Please refresh the page and try again.');
        }
        if (!applicationId) {
            throw createError('The application ID is missing. Please refresh the page and try again.');
        }
        if (!csrfToken) {
            throw createError('Your security token has expired. Please refresh the page and try again.');
        }

        parameters.set('action', action);
        parameters.set('application_id', applicationId);
        parameters.set(csrfField, csrfToken);
        return { actionUrl: actionUrl, body: parameters.toString() };
    }

    function payloadMessage(payload, fallback) {
        if (!payload || typeof payload !== 'object') {
            return fallback;
        }
        return payload.message || payload.error_message || payload.error || fallback;
    }

    async function postAction(root, action) {
        var request = appendParameters(root, action);
        var response;
        var raw = '';
        var payload = {};

        try {
            response = await window.fetch(request.actionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: request.body
            });
            raw = await response.text();
        } catch (error) {
            throw createError('Unable to contact the server. Check your connection and try again.');
        }

        if (raw) {
            try {
                payload = JSON.parse(raw);
            } catch (error) {
                if (!response.ok) {
                    throw createError('The server could not complete the request. Please try again.');
                }
                throw createError('The server returned an unexpected response. Please refresh the page and try again.');
            }
        }

        if (!response.ok || payload.success === false || payload.ok === false || payload.status === 'error' || payload.error === true || (payload.error && !payload.success)) {
            throw createError(payloadMessage(payload, 'The server could not complete the request. Please try again.'));
        }

        return payload;
    }

    function responseValue(payload, names) {
        var containers = [payload];
        var keys = ['data', 'card', 'id_card', 'idCard', 'result'];
        var i;
        var j;
        var value;

        for (i = 0; i < keys.length; i += 1) {
            if (payload && payload[keys[i]] && typeof payload[keys[i]] === 'object') {
                containers.push(payload[keys[i]]);
            }
        }

        for (i = 0; i < containers.length; i += 1) {
            for (j = 0; j < names.length; j += 1) {
                value = containers[i] && containers[i][names[j]];
                if (value !== undefined && value !== null && value !== '') {
                    return String(value);
                }
            }
        }
        return '';
    }

    function applyApprovedDates(root, payload) {
        var issue = responseValue(payload, ['issue_date', 'issueDate', 'id_card_issue_date', 'issue']);
        var validUntil = responseValue(payload, ['valid_until', 'validUntil', 'valid_until_date', 'id_card_valid_until', 'valid_to', 'expiry_date']);

        if (!issue || !validUntil) {
            throw createError('The approval response did not include the ID card issue and validity dates. Please try again.');
        }

        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-issue]'), function (element) {
            element.textContent = issue;
        });
        Array.prototype.forEach.call(root.querySelectorAll('[data-id-card-valid-until]'), function (element) {
            element.textContent = validUntil;
        });
    }

    function saveStyle(element) {
        return {
            width: element.style.width,
            height: element.style.height,
            maxWidth: element.style.maxWidth,
            minWidth: element.style.minWidth,
            transform: element.style.transform,
            margin: element.style.margin
        };
    }

    function restoreStyle(element, style) {
        element.style.width = style.width;
        element.style.height = style.height;
        element.style.maxWidth = style.maxWidth;
        element.style.minWidth = style.minWidth;
        element.style.transform = style.transform;
        element.style.margin = style.margin;
    }

    function canvasToBlob(canvas) {
        return new Promise(function (resolve, reject) {
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        resolve(blob);
                    } else {
                        reject(createError('The ID card image could not be created. Please try again.'));
                    }
                }, 'image/png');
                return;
            }

            try {
                var dataUrl = canvas.toDataURL('image/png');
                var binary = window.atob(dataUrl.split(',')[1]);
                var bytes = new Uint8Array(binary.length);
                var index;
                for (index = 0; index < binary.length; index += 1) {
                    bytes[index] = binary.charCodeAt(index);
                }
                resolve(new Blob([bytes], { type: 'image/png' }));
            } catch (error) {
                reject(createError('The ID card image could not be created. Please try again.'));
            }
        });
    }

    async function waitForAssets(card) {
        var images = card.querySelectorAll('img');
        var waits = [];
        Array.prototype.forEach.call(images, function (image) {
            if (image.complete) {
                if (image.naturalWidth > 0) {
                    return;
                }
                waits.push(Promise.reject(createError('An ID card image could not be loaded.')));
                return;
            }
            waits.push(new Promise(function (resolve, reject) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', function () { reject(createError('An ID card image could not be loaded.')); }, { once: true });
            }));
        });
        if (document.fonts && document.fonts.ready) {
            waits.push(document.fonts.ready);
        }
        await Promise.all(waits);
    }

    async function captureCard(card) {
        var style = saveStyle(card);

        await waitForAssets(card);

        card.classList.add('id-card-capturing');
        card.style.width = CARD_WIDTH + 'px';
        card.style.height = CARD_HEIGHT + 'px';
        card.style.maxWidth = 'none';
        card.style.minWidth = CARD_WIDTH + 'px';
        card.style.transform = 'none';
        card.style.margin = '0';

        try {
            var canvas = await window.html2canvas(card, {
                scale: 3,
                useCORS: true,
                allowTaint: false,
                backgroundColor: '#ffffff',
                logging: false,
                width: CARD_WIDTH,
                height: CARD_HEIGHT,
                windowWidth: CARD_WIDTH,
                windowHeight: CARD_HEIGHT,
                scrollX: 0,
                scrollY: 0
            });
            return await canvasToBlob(canvas);
        } finally {
            restoreStyle(card, style);
            card.classList.remove('id-card-capturing');
        }
    }

    function blobAsDataUrl(blob) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onerror = function () {
                reject(createError('The ID card PDF could not be prepared. Please try again.'));
            };
            reader.onload = function () {
                resolve(reader.result);
            };
            reader.readAsDataURL(blob);
        });
    }

    async function makePdf(frontBlob, backBlob) {
        var jsPDF = window.jspdf.jsPDF;
        var frontImage = await blobAsDataUrl(frontBlob);
        var backImage = await blobAsDataUrl(backBlob);
        var pdf = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: [CARD_WIDTH_MM, CARD_HEIGHT_MM],
            compress: true
        });

        pdf.addImage(frontImage, 'PNG', 0, 0, CARD_WIDTH_MM, CARD_HEIGHT_MM);
        pdf.addPage([CARD_WIDTH_MM, CARD_HEIGHT_MM], 'portrait');
        pdf.addImage(backImage, 'PNG', 0, 0, CARD_WIDTH_MM, CARD_HEIGHT_MM);
        return pdf.output('blob');
    }

    async function makeZip(reference, frontBlob, backBlob, pdfBlob) {
        var zip = new window.JSZip();
        var baseName = 'RTTC_ID_CARD_' + reference;

        zip.file(baseName + '_FRONT.png', frontBlob);
        zip.file(baseName + '_BACK.png', backBlob);
        zip.file(baseName + '.pdf', pdfBlob);
        return zip.generateAsync({
            type: 'blob',
            compression: 'DEFLATE',
            compressionOptions: { level: 6 }
        });
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
        window.setTimeout(function () {
            window.URL.revokeObjectURL(objectUrl);
        }, 1000);
    }

    function cardNodes(root) {
        return {
            front: root.querySelector('#id-card-front'),
            back: root.querySelector('#id-card-back')
        };
    }

    async function exportCard(button, root) {
        var status;
        var cards;
        var reference;
        var frontBlob;
        var backBlob;
        var pdfBlob;
        var zipBlob;

        if (!librariesAvailable()) {
            showMessage(root, 'ID card export is unavailable because the required download libraries have not finished loading. Please wait a moment and try again.', 'error');
            return;
        }

        cards = cardNodes(root);
        if (!cards.front || !cards.back) {
            showMessage(root, 'Both sides of the ID card must be available before it can be exported.', 'error');
            return;
        }

        reference = root.dataset.reference;
        if (!reference) {
            showMessage(root, 'The ID card reference is missing. Please refresh the page and try again.', 'error');
            return;
        }

        status = getStatus(root);
        if (status !== 'pending' && status !== 'approved' && status !== 'done') {
            showMessage(root, 'This ID card is not ready to export yet.', 'error');
            return;
        }

        clearMessage(root);
        setLoading(button, status === 'pending' ? 'Approving...' : 'Preparing...');

        try {
            if (status === 'pending') {
                var approval = await postAction(root, 'approve');
                applyApprovedDates(root, approval);
                setStatus(root, 'approved');
                status = 'approved';
            }

            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Capturing...';
            try {
                frontBlob = await captureCard(cards.front);
                backBlob = await captureCard(cards.back);
            } catch (error) {
                throw createError('The ID card images could not be captured. Please refresh the page and try again.');
            }

            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Packaging...';
            try {
                pdfBlob = await makePdf(frontBlob, backBlob);
                zipBlob = await makeZip(reference, frontBlob, backBlob, pdfBlob);
            } catch (error) {
                throw createError('The ID card download package could not be generated. Please try again.');
            }

            triggerDownload(zipBlob, 'RTTC_ID_CARD_' + reference + '.zip');

            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Finalising...';
            try {
                await postAction(root, 'mark_done');
                setStatus(root, 'done');
            } catch (error) {
                throw createError('The files were downloaded, but the ID card could not be marked as done. Please refresh the page and try again.');
            }

            showMessage(root, 'The ID card package has been downloaded successfully.', 'success');
        } catch (error) {
            showMessage(root, errorMessage(error, 'The ID card could not be exported. Please try again.'), 'error');
        } finally {
            resetButton(button);
        }
    }

    async function deleteCard(button, root) {
        var holder = root.dataset.holderName || 'this applicant';
        var reference = root.dataset.reference || 'this ID card';
        if (!window.confirm('Delete pending application ' + reference + ' for ' + holder + '? This action cannot be undone.')) {
            return;
        }

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
        Array.prototype.forEach.call(document.querySelectorAll('[data-id-card-export]'), function (button) {
            if (button.dataset.idCardExportBound === 'true') {
                return;
            }
            button.dataset.idCardExportBound = 'true';
            button.addEventListener('click', function (event) {
                var root = getRoot(button);
                event.preventDefault();
                if (root) {
                    exportCard(button, root);
                }
            });
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-id-card-delete]'), function (button) {
            if (button.dataset.idCardDeleteBound === 'true') {
                return;
            }
            button.dataset.idCardDeleteBound = 'true';
            button.addEventListener('click', function (event) {
                var root = getRoot(button);
                event.preventDefault();
                if (root) {
                    deleteCard(button, root);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise);
    } else {
        initialise();
    }
})();
