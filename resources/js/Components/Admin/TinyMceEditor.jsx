import MediaPicker from '@/Components/Admin/MediaPicker';
import { Editor } from '@tinymce/tinymce-react';
import { useRef, useState } from 'react';

export default function TinyMceEditor({
    value = '',
    onChange,
    height = 480,
    dir = 'rtl',
}) {
    const editorRef = useRef(null);
    const [pickerOpen, setPickerOpen] = useState(false);

    const uploadHandler = (blobInfo, progress) =>
        new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', route('admin.media.upload'));
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (csrf) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            }
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable && progress) {
                    progress((e.loaded / e.total) * 100);
                }
            };
            xhr.onload = () => {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(`HTTP ${xhr.status}`);
                    return;
                }
                try {
                    const json = JSON.parse(xhr.responseText);
                    if (!json.location) {
                        reject(json.message || 'Invalid JSON');
                        return;
                    }
                    resolve(json.location);
                } catch {
                    reject('Invalid JSON');
                }
            };
            xhr.onerror = () => reject('Upload failed');
            xhr.send(formData);
        });

    return (
        <>
            <Editor
                tinymceScriptSrc="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"
                value={value || ''}
                onEditorChange={(content) => onChange?.(content)}
                onInit={(_evt, editor) => {
                    editorRef.current = editor;
                }}
                init={{
                    height,
                    menubar: false,
                    branding: false,
                    directionality: dir === 'rtl' ? 'rtl' : 'ltr',
                    language: 'ar',
                    plugins:
                        'lists link image media table code codesample fullscreen directionality',
                    toolbar:
                        'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist | link image medialibrary media table | code fullscreen',
                    images_upload_handler: uploadHandler,
                    automatic_uploads: true,
                    file_picker_types: 'image',
                    setup: (editor) => {
                        editor.ui.registry.addButton('medialibrary', {
                            icon: 'gallery',
                            tooltip: 'مكتبة الوسائط',
                            onAction: () => setPickerOpen(true),
                        });
                    },
                    content_style: `
                        body {
                            font-family: "Segoe UI", Tahoma, "Noto Naskh Arabic", "Traditional Arabic", Arial, sans-serif;
                            font-size: 16px;
                            line-height: 1.8;
                            direction: ${dir};
                        }
                    `,
                }}
            />

            <MediaPicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                onSelect={(item) => {
                    const editor = editorRef.current;
                    if (!editor || !item?.url) {
                        return;
                    }
                    const alt = item.alt || item.title || '';
                    editor.insertContent(`<img src="${item.url}" alt="${alt.replace(/"/g, '&quot;')}" />`);
                }}
            />
        </>
    );
}
