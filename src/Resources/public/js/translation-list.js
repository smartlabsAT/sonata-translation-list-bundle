/**
 * SonataTranslationListBundle
 *
 * - Multi-field toggle (show/hide columns without page reload)
 * - Auto-save translation textareas on blur
 * - Hides original table header and language switcher in translation mode
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

        // --- Hide original table header and language switcher ---
        hideOriginalElements();

        // --- Field toggle buttons ---
        initFieldToggles();

        // --- Textarea save behavior ---
        initTextareas(saveUrl, csrfToken);
    }

    /**
     * Hide the original Sonata list thead and the locale_switcher.
     */
    function hideOriginalElements() {
        var thead = document.querySelector('table.sonata-ba-list thead');
        if (thead) {
            thead.style.display = 'none';
        }

        document.querySelectorAll('.locale_switcher').forEach(function (el) {
            el.style.display = 'none';
        });
    }

    /**
     * Field toggle buttons: click to show/hide columns for that field.
     * At least one field must remain active.
     */
    function initFieldToggles() {
        var buttons = document.querySelectorAll('.translation-field-toggle');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = this.getAttribute('data-field');
                var isActive = this.classList.contains('btn-primary');
                var activeCount = document.querySelectorAll('.translation-field-toggle.btn-primary').length;

                // Prevent deactivating the last field
                if (isActive && activeCount <= 1) {
                    return;
                }

                // Toggle button state
                this.classList.toggle('btn-primary');
                this.classList.toggle('btn-default');
                var nowActive = !isActive;

                // Show/hide all cells for this field
                document.querySelectorAll('[data-translation-field="' + field + '"]').forEach(function (el) {
                    el.style.display = nowActive ? '' : 'none';
                });

                // Auto-resize newly visible textareas
                if (nowActive) {
                    document.querySelectorAll('[data-translation-field="' + field + '"] textarea').forEach(function (ta) {
                        autoResize(ta);
                    });
                }

                // Update URL without reload (for bookmarkability)
                updateUrlFields();
            });
        });
    }

    /**
     * Update the URL query param `translation_fields` to reflect active fields.
     */
    function updateUrlFields() {
        var active = [];
        document.querySelectorAll('.translation-field-toggle.btn-primary').forEach(function (btn) {
            active.push(btn.getAttribute('data-field'));
        });

        var url = new URL(window.location.href);
        url.searchParams.set('translation_fields', active.join(','));
        url.searchParams.delete('translation_field'); // remove old single-field param
        window.history.replaceState(null, '', url.toString());
    }

    /**
     * Initialize textarea auto-resize, save-on-blur, and keyboard shortcuts.
     */
    function initTextareas(saveUrl, csrfToken) {
        document.querySelectorAll('textarea.translation-editable').forEach(function (textarea) {
            textarea.addEventListener('input', function () {
                autoResize(this);
                updateModifiedState(this);
            });

            textarea.addEventListener('blur', function () {
                var originalValue = this.getAttribute('data-original-value') || '';
                if (this.value !== originalValue) {
                    saveTranslation(this, saveUrl, csrfToken);
                }
            });

            textarea.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });

            autoResize(textarea);
        });

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
