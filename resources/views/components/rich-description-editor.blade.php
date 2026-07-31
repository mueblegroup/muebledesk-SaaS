@props(['name' => null, 'nameExpression' => null, 'model' => 'item.description'])

<div x-data="richDescriptionEditor({ modelValue: {{ $model }} })" x-init="init($refs.editor)" class="min-w-[320px] space-y-2">
    @if($nameExpression)
        <input type="hidden" x-bind:name="{{ $nameExpression }}" x-model="value">
    @else
        <input type="hidden" name="{{ $name }}" x-model="value">
    @endif

    <div class="flex flex-wrap gap-1 rounded-2xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-800 dark:bg-slate-900">
        <button type="button" class="rounded-xl px-2 py-1 text-xs font-bold hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="command('bold')" title="Bold">B</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs italic hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="command('italic')" title="Italic">I</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs underline hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="command('underline')" title="Underline">U</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="command('insertUnorderedList')" title="Bullet list">• List</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="command('insertOrderedList')" title="Numbered list">1. List</button>
        <input type="color" class="h-7 w-9 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-800" title="Text colour" @mousedown="saveSelection()" @input="command('foreColor', $event.target.value)">
        <button type="button" class="rounded-xl px-2 py-1 text-xs hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="insertLink()">Link</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="insertImage()">Image URL</button>
        <button type="button" class="rounded-xl px-2 py-1 text-xs text-slate-500 hover:bg-white dark:hover:bg-slate-800" @mousedown.prevent="clearFormatting()">Clear</button>
    </div>

    <div x-ref="editor"
        contenteditable="true"
        class="rich-description-input min-h-[100px] rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm leading-relaxed text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-100"
        @input="syncFromEditor()"
        @keyup="saveSelection()"
        @mouseup="saveSelection()"
        @focus="saveSelection()"
        @blur="syncFromEditor()"
        data-placeholder="Add formatted description, bullet points, links, or image URL...">
    </div>

    <p class="text-[11px] text-slate-500 dark:text-slate-400">Supports bold, italic, underline, bullets, numbering, colours, links, and HTTPS image URLs.</p>
</div>

@once
    @push('scripts')
        <script>
            window.richDescriptionEditor = function ({ modelValue }) {
                return {
                    value: modelValue || '',
                    editor: null,
                    savedRange: null,
                    init(editor) {
                        this.editor = editor;
                        this.editor.innerHTML = this.value || '';
                    },
                    syncFromEditor() {
                        this.value = this.editor ? this.editor.innerHTML : '';
                    },
                    saveSelection() {
                        const selection = window.getSelection();
                        if (!selection || selection.rangeCount === 0 || !this.editor) return;
                        const range = selection.getRangeAt(0);
                        if (this.editor.contains(range.commonAncestorContainer)) {
                            this.savedRange = range.cloneRange();
                        }
                    },
                    restoreSelection() {
                        if (!this.savedRange) return;
                        const selection = window.getSelection();
                        selection.removeAllRanges();
                        selection.addRange(this.savedRange);
                    },
                    command(command, value = null) {
                        if (!this.editor) return;
                        this.editor.focus();
                        this.restoreSelection();
                        document.execCommand(command, false, value);
                        this.saveSelection();
                        this.syncFromEditor();
                    },
                    insertLink() {
                        this.saveSelection();
                        const url = prompt('Enter link URL. Use https://, mailto:, or tel:');
                        if (!url) return;
                        if (!/^(https?:\/\/|mailto:|tel:)/i.test(url)) {
                            alert('Only https://, http://, mailto:, or tel: links are allowed.');
                            return;
                        }
                        this.command('createLink', url);
                    },
                    insertImage() {
                        this.saveSelection();
                        const url = prompt('Enter HTTPS image URL');
                        if (!url) return;
                        if (!/^https:\/\//i.test(url)) {
                            alert('Only HTTPS image URLs are allowed.');
                            return;
                        }
                        this.command('insertImage', url);
                    },
                    clearFormatting() {
                        this.command('removeFormat');
                    }
                }
            }
        </script>
        <style>
            [contenteditable][data-placeholder]:empty::before {
                content: attr(data-placeholder);
                color: #94a3b8;
            }
            .rich-description-input ul,
            .document-rich-text ul { list-style: disc; padding-left: 1.5rem; margin: .35rem 0; }
            .rich-description-input ol,
            .document-rich-text ol { list-style: decimal; padding-left: 1.5rem; margin: .35rem 0; }
            .rich-description-input li,
            .document-rich-text li { margin: .15rem 0; }
            .rich-description-input a,
            .document-rich-text a { color: #4f46e5; text-decoration: underline; }
            .rich-description-input img,
            .document-rich-text img { max-width: 160px; max-height: 120px; display: block; margin-top: 6px; border-radius: 8px; }
        </style>
    @endpush
@endonce