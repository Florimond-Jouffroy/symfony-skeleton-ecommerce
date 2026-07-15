import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import List from '@editorjs/list';
import Quote from '@editorjs/quote';
import Code from '@editorjs/code';
import Delimiter from '@editorjs/delimiter';
import EditorImageTool from './EditorImageTool';

const BlockEditor = forwardRef(function BlockEditor({ value, onChange, onUploadFile }, ref) {
    const holderRef    = useRef(null);
    const editorRef    = useRef(null);
    const onChangeRef  = useRef(onChange);
    const onUploadRef  = useRef(onUploadFile);

    onChangeRef.current = onChange;
    onUploadRef.current = onUploadFile;

    useImperativeHandle(ref, () => ({
        insertImage: (url) => {
            editorRef.current?.blocks.insert('image', {
                file: { url },
                caption: '',
                width: '100',
            });
        },
    }));

    useEffect(() => {
        if (!holderRef.current || editorRef.current) return;

        // Injected here (not in global CSS) so it lands in the DOM *after*
        // Editor.js's own <style> tag, guaranteeing our rules win the cascade.
        if (!document.getElementById('editorjs-fullwidth')) {
            const style = document.createElement('style');
            style.id = 'editorjs-fullwidth';
            style.textContent =
                '.codex-editor__redactor{margin-left:0!important;margin-right:0!important}' +
                '.ce-block__content,.ce-toolbar__content{max-width:none!important;margin-left:0!important;margin-right:0!important}' +
                '@media(min-width:768px){.codex-editor{margin-left:4em}}';
            document.head.appendChild(style);
        }

        const editor = new EditorJS({
            holder: holderRef.current,
            data: value ?? { blocks: [] },
            placeholder: 'Commencez à rédiger votre article…',
            tools: {
                header: {
                    class: Header,
                    inlineToolbar: true,
                    config: { levels: [2, 3, 4], defaultLevel: 2 },
                },
                list: {
                    class: List,
                    inlineToolbar: true,
                },
                quote: {
                    class: Quote,
                    inlineToolbar: true,
                },
                code: Code,
                delimiter: Delimiter,
                image: {
                    class: EditorImageTool,
                    config: {
                        uploader: {
                            uploadByFile: (file) => onUploadRef.current(file),
                        },
                    },
                },
            },
            onChange: async (api) => {
                const data = await api.saver.save();
                onChangeRef.current(data);
            },
        });

        editorRef.current = editor;

        return () => {
            const instance = editorRef.current;
            editorRef.current = null;
            if (instance) {
                instance.isReady
                    .then(() => instance.destroy())
                    .catch(() => {});
            }
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div
            ref={holderRef}
            className="min-h-64 rounded-md border bg-background px-4 py-3 text-sm
                [&_.ce-block]:py-0.5
                [&_.ce-toolbar__plus]:text-muted-foreground
                [&_.ce-toolbar__settings-btn]:text-muted-foreground
                [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:my-2
                [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:my-2
                [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:my-1
                [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:italic
                [&_ul]:list-disc [&_ul]:pl-6
                [&_ol]:list-decimal [&_ol]:pl-6"
        />
    );
});

export default BlockEditor;
