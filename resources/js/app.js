/**
 * The description designer.
 *
 * A shopkeeper types into the box and clicks the buttons; someone who prefers
 * to write HTML can switch to the code view. Whatever comes out is cleaned on
 * the server before it is stored, so nothing typed here can ever run.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('htmlEditor', (initial, statePath) => ({
        html: initial || '',
        codeView: false,

        init() {
            this.$refs.editor.innerHTML = this.html;
        },

        push() {
            this.$wire.set(statePath, this.html, false);
        },

        fromEditor() {
            this.html = this.$refs.editor.innerHTML;
            this.push();
        },

        fromCode() {
            this.push();
        },

        toggleCode() {
            if (this.codeView) {
                // Coming back from the code view: show what was typed.
                this.$refs.editor.innerHTML = this.html;
            } else {
                this.html = this.$refs.editor.innerHTML;
            }

            this.codeView = !this.codeView;
        },

        run(command, value = null) {
            this.$refs.editor.focus();
            document.execCommand(command, false, value);
            this.fromEditor();
        },

        block(tag) {
            this.run('formatBlock', tag);
        },

        link() {
            const url = window.prompt('Where should this link go?', 'https://');

            if (url) {
                this.run('createLink', url);
            }
        },

        insertImage(url) {
            this.run('insertImage', url);
        },

        clean() {
            this.run('removeFormat');
        },
    }));
});
