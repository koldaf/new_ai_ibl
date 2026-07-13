import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Underline from '@tiptap/extension-underline';
import Image from '@tiptap/extension-image';
import { Table } from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import TextAlign from '@tiptap/extension-text-align';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';

const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: null,
                parseHTML: (element) => element.getAttribute('width'),
                renderHTML: (attributes) => {
                    if (!attributes.width) {
                        return {};
                    }

                    return {
                        width: attributes.width,
                    };
                },
            },
        };
    },
});

function isSafeHref(href) {
    if (!href) {
        return false;
    }

    if (href.startsWith('#') || href.startsWith('/')) {
        return true;
    }

    return /^(https?:|mailto:)/i.test(href);
}

function isSafeImageSrc(src) {
    if (!src) {
        return false;
    }

    if (src.startsWith('/')) {
        return true;
    }

    if (/^https?:\/\//i.test(src)) {
        return true;
    }

    return /^data:image\/(png|jpe?g|gif|webp);base64,/i.test(src);
}

function readFileAsDataUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => reject(new Error('Unable to read image file.'));
        reader.readAsDataURL(file);
    });
}

function getEditorImageUploadUrl() {
    const meta = document.querySelector('meta[name="editor-image-upload-url"]');
    return meta ? String(meta.getAttribute('content') || '') : '';
}

async function uploadImageFile(file) {
    const uploadUrl = getEditorImageUploadUrl();

    if (!uploadUrl) {
        return String(await readFileAsDataUrl(file));
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('image', file);

    const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: formData,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || 'Image upload failed.');
    }

    const json = await response.json();
    const url = json && json.data && json.data.url ? String(json.data.url) : '';

    if (!isSafeImageSrc(url)) {
        throw new Error('Image upload returned an invalid URL.');
    }

    return url;
}

function createToolbarSeparator(toolbar) {
    const separator = document.createElement('span');
    separator.className = 'tiptap-toolbar-separator';
    toolbar.appendChild(separator);
}

function createToolbarButton({ title, label, command, isActive }) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary tiptap-toolbar-button';
    button.textContent = label;
    button.title = title;

    button.addEventListener('click', () => {
        command();
        refresh();
    });

    function refresh() {
        if (typeof isActive === 'function' && isActive()) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    }

    return { button, refresh };
}

function setupEditor(textarea) {
    if (textarea.dataset.tiptapInitialized === '1') {
        return;
    }

    textarea.dataset.tiptapInitialized = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'tiptap-editor-wrapper';

    const toolbar = document.createElement('div');
    toolbar.className = 'tiptap-toolbar d-flex flex-wrap align-items-center gap-1';

    const content = document.createElement('div');
    content.className = 'tiptap-content form-control';

    const source = document.createElement('textarea');
    source.className = 'form-control tiptap-source-view d-none';
    source.rows = Number(textarea.getAttribute('rows') || 8);

    textarea.classList.add('d-none');
    textarea.parentNode.insertBefore(wrapper, textarea);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(content);
    wrapper.appendChild(source);
    wrapper.appendChild(textarea);

    const imageInput = document.createElement('input');
    imageInput.type = 'file';
    imageInput.accept = 'image/png,image/jpeg,image/jpg,image/gif,image/webp';
    imageInput.className = 'd-none';
    wrapper.appendChild(imageInput);

    const editor = new Editor({
        element: content,
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [1, 2, 3, 4],
                },
            }),
            Underline,
            Subscript,
            Superscript,
            Link.configure({
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
            }),
            TextAlign.configure({
                types: ['heading', 'paragraph'],
            }),
            ResizableImage.configure({
                allowBase64: true,
            }),
            Table.configure({
                resizable: true,
            }),
            TableRow,
            TableHeader,
            TableCell,
            Placeholder.configure({
                placeholder: textarea.getAttribute('placeholder') || 'Input your content here...',
            }),
        ],
        content: textarea.value || '',
        editorProps: {
            handlePaste: (_view, event) => {
                const clipboardData = event.clipboardData;
                const html = clipboardData?.getData('text/html') || '';
                const text = clipboardData?.getData('text/plain') || '';

                if (html.trim() || text.trim()) {
                    return false;
                }

                const clipboardItems = clipboardData?.items || [];
                for (const item of clipboardItems) {
                    if (!item.type.startsWith('image/')) {
                        continue;
                    }

                    const file = item.getAsFile();
                    if (!file) {
                        continue;
                    }

                    event.preventDefault();

                    uploadImageFile(file)
                        .then((src) => {
                            if (!isSafeImageSrc(src)) {
                                return;
                            }

                            editor.chain().focus().setImage({
                                src,
                                alt: file.name || 'Pasted image',
                            }).run();
                        })
                        .catch(() => {
                            window.alert('Unable to paste this image.');
                        });

                    return true;
                }

                return false;
            },
        },
        onUpdate: ({ editor: currentEditor }) => {
            if (source.classList.contains('d-none')) {
                textarea.value = currentEditor.getHTML();
            }
            updateToolbarState();
        },
        onSelectionUpdate: () => {
            updateToolbarState();
        },
    });

    let sourceMode = false;

    function syncToTextarea() {
        if (sourceMode) {
            textarea.value = source.value;
            return;
        }

        textarea.value = editor.getHTML();
    }

    const controls = [];

    function addButton(config) {
        const control = createToolbarButton(config);
        controls.push(control);
        toolbar.appendChild(control.button);
    }

    function updateToolbarState() {
        controls.forEach((control) => control.refresh());
    }

    addButton({
        title: 'Undo',
        label: 'Undo',
        command: () => editor.chain().focus().undo().run(),
        isActive: () => false,
    });

    addButton({
        title: 'Redo',
        label: 'Redo',
        command: () => editor.chain().focus().redo().run(),
        isActive: () => false,
    });

    createToolbarSeparator(toolbar);

    const headingSelect = document.createElement('select');
    headingSelect.className = 'form-select form-select-sm tiptap-select';
    headingSelect.innerHTML = [
        '<option value="paragraph">Paragraph</option>',
        '<option value="h1">Heading 1</option>',
        '<option value="h2">Heading 2</option>',
        '<option value="h3">Heading 3</option>',
        '<option value="h4">Heading 4</option>',
    ].join('');

    headingSelect.addEventListener('change', () => {
        const value = headingSelect.value;
        const chain = editor.chain().focus();

        if (value === 'paragraph') {
            chain.setParagraph().run();
            return;
        }

        const level = Number(value.replace('h', ''));
        chain.toggleHeading({ level }).run();
    });

    controls.push({
        refresh: () => {
            if (editor.isActive('heading', { level: 1 })) {
                headingSelect.value = 'h1';
                return;
            }

            if (editor.isActive('heading', { level: 2 })) {
                headingSelect.value = 'h2';
                return;
            }

            if (editor.isActive('heading', { level: 3 })) {
                headingSelect.value = 'h3';
                return;
            }

            if (editor.isActive('heading', { level: 4 })) {
                headingSelect.value = 'h4';
                return;
            }

            headingSelect.value = 'paragraph';
        },
    });

    toolbar.appendChild(headingSelect);

    addButton({
        title: 'Bold',
        label: 'B',
        command: () => editor.chain().focus().toggleBold().run(),
        isActive: () => editor.isActive('bold'),
    });

    addButton({
        title: 'Italic',
        label: 'I',
        command: () => editor.chain().focus().toggleItalic().run(),
        isActive: () => editor.isActive('italic'),
    });

    addButton({
        title: 'Underline',
        label: 'U',
        command: () => editor.chain().focus().toggleUnderline().run(),
        isActive: () => editor.isActive('underline'),
    });

    addButton({
        title: 'Strikethrough',
        label: 'S',
        command: () => editor.chain().focus().toggleStrike().run(),
        isActive: () => editor.isActive('strike'),
    });

    addButton({
        title: 'Superscript',
        label: 'X2',
        command: () => editor.chain().focus().toggleSuperscript().run(),
        isActive: () => editor.isActive('superscript'),
    });

    addButton({
        title: 'Subscript',
        label: 'X2_',
        command: () => editor.chain().focus().toggleSubscript().run(),
        isActive: () => editor.isActive('subscript'),
    });

    createToolbarSeparator(toolbar);

    addButton({
        title: 'Align Left',
        label: 'Left',
        command: () => editor.chain().focus().setTextAlign('left').run(),
        isActive: () => editor.isActive({ textAlign: 'left' }),
    });

    addButton({
        title: 'Align Center',
        label: 'Center',
        command: () => editor.chain().focus().setTextAlign('center').run(),
        isActive: () => editor.isActive({ textAlign: 'center' }),
    });

    addButton({
        title: 'Align Right',
        label: 'Right',
        command: () => editor.chain().focus().setTextAlign('right').run(),
        isActive: () => editor.isActive({ textAlign: 'right' }),
    });

    addButton({
        title: 'Justify',
        label: 'Justify',
        command: () => editor.chain().focus().setTextAlign('justify').run(),
        isActive: () => editor.isActive({ textAlign: 'justify' }),
    });

    createToolbarSeparator(toolbar);

    addButton({
        title: 'Bullet List',
        label: '• List',
        command: () => editor.chain().focus().toggleBulletList().run(),
        isActive: () => editor.isActive('bulletList'),
    });

    addButton({
        title: 'Ordered List',
        label: '1. List',
        command: () => editor.chain().focus().toggleOrderedList().run(),
        isActive: () => editor.isActive('orderedList'),
    });

    addButton({
        title: 'Quote',
        label: 'Quote',
        command: () => editor.chain().focus().toggleBlockquote().run(),
        isActive: () => editor.isActive('blockquote'),
    });

    addButton({
        title: 'Code Block',
        label: 'Code',
        command: () => editor.chain().focus().toggleCodeBlock().run(),
        isActive: () => editor.isActive('codeBlock'),
    });

    addButton({
        title: 'Horizontal Rule',
        label: 'Rule',
        command: () => editor.chain().focus().setHorizontalRule().run(),
        isActive: () => false,
    });

    createToolbarSeparator(toolbar);

    addButton({
        title: 'Link',
        label: 'Link',
        command: () => {
            const previous = editor.getAttributes('link').href || '';
            const href = window.prompt('Enter URL', previous);

            if (href === null) {
                return;
            }

            if (href.trim() === '') {
                editor.chain().focus().unsetLink().run();
                return;
            }

            const normalized = href.trim();
            if (!isSafeHref(normalized)) {
                window.alert('Only http, https, mailto, root-relative, and anchor links are allowed.');
                return;
            }

            editor.chain().focus().setLink({ href: normalized }).run();
        },
        isActive: () => editor.isActive('link'),
    });

    addButton({
        title: 'Insert Image',
        label: 'Image',
        command: () => {
            imageInput.value = '';
            imageInput.click();
        },
        isActive: () => editor.isActive('image'),
    });

    imageInput.addEventListener('change', (event) => {
        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
        if (!file) {
            return;
        }

        uploadImageFile(file)
            .then((src) => {
                if (!isSafeImageSrc(src)) {
                    window.alert('Invalid image type. Please use PNG, JPG, GIF, or WEBP.');
                    return;
                }

                editor.chain().focus().setImage({ src, alt: file.name || 'Uploaded image' }).run();
            })
            .catch(() => {
                window.alert('Unable to insert image.');
            });
    });

    addButton({
        title: 'Insert Image URL',
        label: 'Image URL',
        command: () => {
            const src = window.prompt('Enter image URL');
            if (!src) {
                return;
            }

            const trimmed = src.trim();
            if (!isSafeImageSrc(trimmed)) {
                window.alert('Only https, root-relative, or data:image URLs are allowed.');
                return;
            }

            editor.chain().focus().setImage({ src: trimmed }).run();
        },
        isActive: () => false,
    });

    addButton({
        title: 'Image Small (320px)',
        label: 'Img S',
        command: () => {
            if (!editor.isActive('image')) {
                window.alert('Select an image first to resize it.');
                return;
            }

            editor.chain().focus().updateAttributes('image', { width: '320' }).run();
        },
        isActive: () => editor.isActive('image', { width: '320' }),
    });

    addButton({
        title: 'Image Medium (640px)',
        label: 'Img M',
        command: () => {
            if (!editor.isActive('image')) {
                window.alert('Select an image first to resize it.');
                return;
            }

            editor.chain().focus().updateAttributes('image', { width: '640' }).run();
        },
        isActive: () => editor.isActive('image', { width: '640' }),
    });

    addButton({
        title: 'Image Large (960px)',
        label: 'Img L',
        command: () => {
            if (!editor.isActive('image')) {
                window.alert('Select an image first to resize it.');
                return;
            }

            editor.chain().focus().updateAttributes('image', { width: '960' }).run();
        },
        isActive: () => editor.isActive('image', { width: '960' }),
    });

    addButton({
        title: 'Image Full Width',
        label: 'Img Full',
        command: () => {
            if (!editor.isActive('image')) {
                window.alert('Select an image first to resize it.');
                return;
            }

            editor.chain().focus().updateAttributes('image', { width: null }).run();
        },
        isActive: () => editor.isActive('image') && !editor.getAttributes('image').width,
    });

    createToolbarSeparator(toolbar);

    addButton({
        title: 'Insert Table',
        label: 'Table',
        command: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(),
        isActive: () => editor.isActive('table'),
    });

    addButton({
        title: 'Add Row',
        label: 'Row+',
        command: () => editor.chain().focus().addRowAfter().run(),
        isActive: () => false,
    });

    addButton({
        title: 'Add Column',
        label: 'Col+',
        command: () => editor.chain().focus().addColumnAfter().run(),
        isActive: () => false,
    });

    addButton({
        title: 'Delete Row',
        label: 'Row-',
        command: () => editor.chain().focus().deleteRow().run(),
        isActive: () => false,
    });

    addButton({
        title: 'Delete Column',
        label: 'Col-',
        command: () => editor.chain().focus().deleteColumn().run(),
        isActive: () => false,
    });

    addButton({
        title: 'Delete Table',
        label: 'Table-',
        command: () => editor.chain().focus().deleteTable().run(),
        isActive: () => false,
    });

    createToolbarSeparator(toolbar);

    addButton({
        title: 'Clear Formatting',
        label: 'Clear',
        command: () => editor.chain().focus().unsetAllMarks().clearNodes().run(),
        isActive: () => false,
    });

    const sourceToggle = document.createElement('button');
    sourceToggle.type = 'button';
    sourceToggle.className = 'btn btn-sm btn-outline-dark tiptap-source-toggle';
    sourceToggle.textContent = 'HTML';

    sourceToggle.addEventListener('click', () => {
        sourceMode = !sourceMode;

        if (sourceMode) {
            source.value = editor.getHTML();
            source.classList.remove('d-none');
            content.classList.add('d-none');
            sourceToggle.classList.add('active');
        } else {
            editor.commands.setContent(source.value || '', false);
            textarea.value = editor.getHTML();
            source.classList.add('d-none');
            content.classList.remove('d-none');
            sourceToggle.classList.remove('active');
            editor.commands.focus('end');
        }
    });

    source.addEventListener('input', () => {
        if (sourceMode) {
            textarea.value = source.value;
        }
    });

    toolbar.appendChild(sourceToggle);
    updateToolbarState();

    const parentForm = textarea.closest('form');
    if (parentForm && !parentForm.dataset.tiptapBound) {
        parentForm.addEventListener('submit', () => {
            parentForm.querySelectorAll('.wysiwyg-editor[data-tiptap-initialized="1"]').forEach((field) => {
                const wrapper = field.parentElement;
                const sourceView = wrapper ? wrapper.querySelector('.tiptap-source-view') : null;
                const contentView = wrapper ? wrapper.querySelector('.tiptap-content') : null;
                const sourceIsVisible = sourceView && !sourceView.classList.contains('d-none');

                if (sourceIsVisible) {
                    field.value = sourceView.value;
                    return;
                }

                if (contentView && contentView.editor && typeof contentView.editor.getHTML === 'function') {
                    field.value = contentView.editor.getHTML();
                }
            });
        });

        parentForm.addEventListener('reset', () => {
            window.setTimeout(() => {
                parentForm.querySelectorAll('.wysiwyg-editor[data-tiptap-initialized="1"]').forEach((field) => {
                    const wrapper = field.parentElement;
                    const sourceView = wrapper ? wrapper.querySelector('.tiptap-source-view') : null;
                    const contentView = wrapper ? wrapper.querySelector('.tiptap-content') : null;

                    if (contentView && contentView.editor && typeof contentView.editor.commands?.setContent === 'function') {
                        contentView.editor.commands.setContent(field.value || '', false);
                    }

                    if (sourceView) {
                        sourceView.value = field.value || '';
                        sourceView.classList.add('d-none');
                    }

                    if (contentView) {
                        contentView.classList.remove('d-none');
                    }

                    const toggle = wrapper ? wrapper.querySelector('.tiptap-source-toggle') : null;
                    if (toggle) {
                        toggle.classList.remove('active');
                    }
                });
            }, 0);
        });

        parentForm.dataset.tiptapBound = '1';
    }

    content.editor = editor;
    syncToTextarea();
}

export function initializeTipTapEditors(selector = '.wysiwyg-editor') {
    document.querySelectorAll(selector).forEach((textarea) => setupEditor(textarea));
}

document.addEventListener('DOMContentLoaded', () => {
    initializeTipTapEditors();
});
