/**
 * SonataTranslationListBundle
 *
 * - Multi-field toggle (show/hide columns without page reload)
 * - Auto-save on blur for text/textarea/number, on change for checkbox
 * - CKEditor: lazy init on focus, destroy on blur (keeps page fast)
 * - Hides original table header and language switcher in translation mode
 */
(function () {
    'use strict';

    var ckeditorLoaded = false;
    var ckeditorLoading = false;
    var ckeditorQueue = [];

    function init() {
        var saveUrlEl = document.getElementById('translation-list-save-url');
        var csrfTokenEl = document.getElementById('translation-list-csrf-token');

        if (!saveUrlEl || !csrfTokenEl) {
            return;
        }

        var saveUrl = saveUrlEl.getAttribute('data-url');
        var csrfToken = csrfTokenEl.getAttribute('data-token');

        hideOriginalElements();
        initFieldToggles();
        initInputs(saveUrl, csrfToken);
        preloadCKEditor();
    }

    // --- CKEditor lazy loading ---

    /**
     * Preload the CKEditor script in the background (non-blocking).
     * Does NOT create any editor instances yet.
     */
    function preloadCKEditor() {
        if (typeof CKEDITOR !== 'undefined') {
            ckeditorLoaded = true;
            return;
        }

        var hasCKEditorFields = document.querySelector('textarea.translation-ckeditor');
        if (!hasCKEditorFields) {
            return;
        }

        ckeditorLoading = true;
        var paths = ['/ckeditor/ckeditor.js', '/bundles/fosckeditor/ckeditor.js'];
        tryLoadScript(paths, 0, function () {
            ckeditorLoaded = true;
            ckeditorLoading = false;
            // Process any textareas that were clicked while loading
            ckeditorQueue.forEach(function (item) {
                createCKEditor(item.textarea, item.saveUrl, item.csrfToken);
            });
            ckeditorQueue = [];
        });
    }

    function tryLoadScript(paths, index, callback) {
        if (index >= paths.length) {
            ckeditorLoading = false;
            return;
        }
        var script = document.createElement('script');
        script.src = paths[index];
        script.onload = callback;
        script.onerror = function () {
            tryLoadScript(paths, index + 1, callback);
        };
        document.head.appendChild(script);
    }

    // --- Hide original Sonata elements ---

    function hideOriginalElements() {
        var thead = document.querySelector('table.sonata-ba-list thead');
        if (thead) {
            thead.style.display = 'none';
        }
        document.querySelectorAll('.locale_switcher').forEach(function (el) {
            el.style.display = 'none';
        });
    }

    // --- Field toggle buttons ---

    function initFieldToggles() {
        document.querySelectorAll('.translation-field-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = this.getAttribute('data-field');
                var isActive = this.classList.contains('btn-primary');
                var activeCount = document.querySelectorAll('.translation-field-toggle.btn-primary').length;

                if (isActive && activeCount <= 1) {
                    return;
                }

                this.classList.toggle('btn-primary');
                this.classList.toggle('btn-default');
                var nowActive = !isActive;

                document.querySelectorAll('[data-translation-field="' + field + '"]').forEach(function (el) {
                    el.style.display = nowActive ? '' : 'none';
                });

                if (nowActive) {
                    document.querySelectorAll('[data-translation-field="' + field + '"] textarea.translation-textarea').forEach(function (ta) {
                        autoResize(ta);
                    });
                }

                updateUrlFields();
            });
        });
    }

    function updateUrlFields() {
        var active = [];
        document.querySelectorAll('.translation-field-toggle.btn-primary').forEach(function (btn) {
            active.push(btn.getAttribute('data-field'));
        });
        var url = new URL(window.location.href);
        url.searchParams.set('translation_fields', active.join(','));
        url.searchParams.delete('translation_field');
        window.history.replaceState(null, '', url.toString());
    }

    // --- Input initialization ---

    function initInputs(saveUrl, csrfToken) {
        // Text/number inputs — save on blur
        document.querySelectorAll('input.translation-editable').forEach(function (input) {
            var fieldType = input.getAttribute('data-field-type');

            if (fieldType === 'boolean') {
                input.addEventListener('change', function () {
                    saveTranslation(this, saveUrl, csrfToken, this.checked ? '1' : '0');
                });
            } else {
                input.addEventListener('blur', function () {
                    var originalValue = this.getAttribute('data-original-value') || '';
                    if (this.value !== originalValue) {
                        saveTranslation(this, saveUrl, csrfToken, this.value);
                    }
                });
                input.addEventListener('input', function () {
                    updateModifiedState(this, this.value);
                });
            }
        });

        // Textareas — save on blur + lazy CKEditor on focus
        document.querySelectorAll('textarea.translation-editable').forEach(function (textarea) {
            textarea.addEventListener('input', function () {
                autoResize(this);
                updateModifiedState(this, this.value);
            });

            textarea.addEventListener('blur', function () {
                if (this.ckeditorInstance) {
                    return; // CKEditor handles its own blur
                }
                var originalValue = this.getAttribute('data-original-value') || '';
                if (this.value !== originalValue) {
                    saveTranslation(this, saveUrl, csrfToken, this.value);
                }
            });

            textarea.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });

            // Lazy CKEditor: on focus, init ALL ckeditor textareas in the same row.
            // Non-CKEditor textareas keep fixed height from CSS.
            if (textarea.classList.contains('translation-ckeditor')) {
                textarea.addEventListener('focus', function () {
                    if (this.ckeditorInstance) {
                        return;
                    }
                    var self = this;
                    var row = this.closest('tr');
                    if (!row) return;

                    var rowTextareas = row.querySelectorAll('textarea.translation-ckeditor');
                    rowTextareas.forEach(function (ta) {
                        if (ta.ckeditorInstance) return;
                        if (ta.offsetParent === null) return;

                        if (ckeditorLoaded) {
                            createCKEditor(ta, saveUrl, csrfToken, ta === self);
                        } else if (ckeditorLoading) {
                            ckeditorQueue.push({ textarea: ta, saveUrl: saveUrl, csrfToken: csrfToken });
                        }
                    });
                });
            }
        });
    }

    // --- CKEditor instance management ---

    function createCKEditor(textarea, saveUrl, csrfToken, shouldFocus) {
        if (typeof CKEDITOR === 'undefined' || textarea.ckeditorInstance) {
            return;
        }

        if (!textarea.id) {
            textarea.id = 'tl_cke_' + Math.random().toString(36).substring(2, 9);
        }

        // Match the textarea's current height so the layout doesn't jump
        var currentHeight = textarea.offsetHeight;

        var editor = CKEDITOR.replace(textarea.id, {
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'tools', items: ['Source'] }
            ],
            height: Math.max(currentHeight - 70, 80), // subtract toolbar height (~70px)
            removePlugins: 'elementspath',
            resize_enabled: true,
            versionCheck: false
        });

        textarea.ckeditorInstance = editor;

        if (shouldFocus) {
            editor.on('instanceReady', function () {
                editor.focus();
            });
        }

        // Save on blur (keep editor alive for row-based editing)
        editor.on('blur', function () {
            var value = editor.getData();
            var originalValue = textarea.getAttribute('data-original-value') || '';
            if (value !== originalValue) {
                saveTranslation(textarea, saveUrl, csrfToken, value);
            }
        });

        editor.on('change', function () {
            updateModifiedState(textarea, editor.getData());
        });
    }

    // --- Utilities ---

    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(Math.max(textarea.scrollHeight, 60), 300) + 'px';
    }

    function updateModifiedState(element, currentValue) {
        var originalValue = element.getAttribute('data-original-value') || '';
        var container = element.closest('td') || element.parentElement;
        if (currentValue !== originalValue) {
            container.classList.add('translation-modified');
        } else {
            container.classList.remove('translation-modified');
        }
    }

    function saveTranslation(element, saveUrl, csrfToken, value) {
        var objectId = element.getAttribute('data-object-id');
        var adminCode = element.getAttribute('data-admin-code');
        var locale = element.getAttribute('data-locale');
        var field = element.getAttribute('data-field');

        var container = element.closest('td') || element.parentElement;
        container.classList.remove('translation-modified', 'translation-saved', 'translation-error');
        container.classList.add('translation-saving');

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
            container.classList.remove('translation-saving');
            if (result.ok && result.data.status === 'ok') {
                element.setAttribute('data-original-value', value);
                container.classList.add('translation-saved');
                setTimeout(function () {
                    container.classList.remove('translation-saved');
                }, 2000);
            } else {
                container.classList.add('translation-error');
                container.title = result.data.message || 'Save failed';
            }
        })
        .catch(function () {
            container.classList.remove('translation-saving');
            container.classList.add('translation-error');
            container.title = 'Network error';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
