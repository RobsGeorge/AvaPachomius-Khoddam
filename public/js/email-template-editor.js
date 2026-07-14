(function () {
    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function initTinyMCE() {
        if (!window.tinymce) {
            return;
        }

        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';

        tinymce.init({
            selector: 'textarea.email-tpl-editor[data-tinymce="1"]',
            menubar: false,
            branding: false,
            height: 220,
            plugins: 'link lists table code directionality',
            toolbar:
                'blocks | bold italic underline forecolor backcolor | ' +
                'alignleft aligncenter alignright | bullist numlist | ' +
                'link table | removeformat | code',
            block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3',
            font_family_formats:
                'Cairo=Cairo,sans-serif; Arial=arial,helvetica,sans-serif; Georgia=georgia,serif; Courier New=courier new,courier,monospace',
            content_style:
                'body { font-family: Cairo, Arial, sans-serif; font-size: 14px; line-height: 1.5; }',
            directionality: isRtl ? 'rtl' : 'ltr',
            convert_urls: false,
            relative_urls: false,
            promotion: false,
            setup(editor) {
                editor.on('change keyup', () => editor.save());
            },
        });
    }

    function editorHtmlFromPanel(panel) {
        const textarea = panel.querySelector('textarea.email-tpl-editor');
        if (!textarea) {
            return '';
        }
        if (window.tinymce) {
            const ed = tinymce.get(textarea.id);
            if (ed) {
                return ed.getContent();
            }
        }
        return textarea.value;
    }

    function ensureTextareaIds() {
        document.querySelectorAll('textarea.email-tpl-editor').forEach((el, index) => {
            if (!el.id) {
                el.id = 'email-tpl-editor-' + index;
            }
        });
    }

    function initPreview() {
        const hub = document.getElementById('email-template-hub');
        if (!hub) {
            return;
        }

        const previewUrl = hub.dataset.previewUrl;
        const modalEl = document.getElementById('emailTemplatePreviewModal');
        if (!previewUrl || !modalEl || !window.bootstrap) {
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const subjectNode = document.getElementById('email-preview-subject');
        const frame = document.getElementById('email-preview-frame');

        hub.querySelectorAll('.email-tpl-preview-btn').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const panel = btn.closest('details');
                if (!panel) {
                    return;
                }
                const subjectInput = panel.querySelector('.email-tpl-subject');
                const family = btn.dataset.family;
                const subject = subjectInput ? subjectInput.value : '';
                const bodyHtml = editorHtmlFromPanel(panel);

                btn.disabled = true;
                try {
                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body: JSON.stringify({
                            family,
                            subject,
                            body_html: bodyHtml,
                        }),
                    });
                    if (!response.ok) {
                        throw new Error('preview failed');
                    }
                    const data = await response.json();
                    subjectNode.textContent = data.subject || '';
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    doc.open();
                    doc.write(
                        '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                            '<style>body{font-family:Cairo,Arial,sans-serif;padding:1rem;line-height:1.55;color:#222}</style>' +
                            '</head><body>' +
                            (data.body_html || '') +
                            '</body></html>'
                    );
                    doc.close();
                    modal.show();
                } catch (e) {
                    if (window.KhoddamUI && typeof KhoddamUI.toast === 'function') {
                        KhoddamUI.toast('Preview failed', 'error');
                    } else {
                        alert('Preview failed');
                    }
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ensureTextareaIds();
        initTinyMCE();
        initPreview();
    });
})();
