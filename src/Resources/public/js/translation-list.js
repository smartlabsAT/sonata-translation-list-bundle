/**
 * SonataTranslationListBundle — auto-save translation textareas on blur.
 *
 * Textareas with class "translation-editable" are watched for changes.
 * When a textarea loses focus and its value differs from the original,
 * the new value is POSTed to the save endpoint via AJAX.
 */
(function () {
    'use strict';

    function init() {
        var saveUrlEl = document.getElementById('translation-list-save-url');
        var csrfTokenEl = document.getElementById('translation-list-csrf-token');

        if (!saveUrlEl || !csrfTokenEl) {
            return; // Not on a translation list page
        }

        var saveUrl = saveUrlEl.getAttribute('data-url');
        var csrfToken = csrfTokenEl.getAttribute('data-token');

        document.querySelectorAll('textarea.translation-editable').forEach(function (textarea) {
            // Auto-resize on input
            textarea.addEventListener('input', function () {
                autoResize(this);
                updateModifiedState(this);
            });

            // Save on blur
            textarea.addEventListener('blur', function () {
                var originalValue = this.getAttribute('data-original-value') || '';
                if (this.value !== originalValue) {
                    saveTranslation(this, saveUrl, csrfToken);
                }
            });

            // Ctrl+Enter / Cmd+Enter to save without leaving
            textarea.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });

            // Initial auto-resize
            autoResize(textarea);
        });

        // Also auto-resize readonly textareas
        document.querySelectorAll('textarea.translation-readonly').forEach(function (textarea) {
            autoResize(textarea);
        });
    }

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(Math.max(textarea.scrollHeight, 60), 300) + 'px';
    }

    function updateModifiedState(textarea) {
        var originalValue = textarea.getAttribute('data-original-value') || '';
        if (textarea.value !== originalValue) {
            textarea.classList.add('translation-modified');
        } else {
            textarea.classList.remove('translation-modified');
        }
    }

    function saveTranslation(textarea, saveUrl, csrfToken) {
        var objectId = textarea.getAttribute('data-object-id');
        var adminCode = textarea.getAttribute('data-admin-code');
        var locale = textarea.getAttribute('data-locale');
        var field = textarea.getAttribute('data-field');
        var value = textarea.value;

        textarea.classList.remove('translation-modified', 'translation-saved', 'translation-error');
        textarea.classList.add('translation-saving');

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                adminCode: adminCode,
                objectId: objectId,
                locale: locale,
                field: field,
                value: value
            })
        })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
            textarea.classList.remove('translation-saving');
            if (result.ok && result.data.status === 'ok') {
                textarea.setAttribute('data-original-value', value);
                textarea.classList.add('translation-saved');
                setTimeout(function () {
                    textarea.classList.remove('translation-saved');
                }, 2000);
            } else {
                textarea.classList.add('translation-error');
                textarea.title = result.data.message || 'Save failed';
            }
        })
        .catch(function () {
            textarea.classList.remove('translation-saving');
            textarea.classList.add('translation-error');
            textarea.title = 'Network error';
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
