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


/**
 * Numbers that count up to their new value instead of jumping.
 *
 * Nothing here reloads the page. When Livewire sends back a new figure, the
 * old one rolls up to it and the box it sits in glows for a moment, so the
 * shopkeeper can see exactly what changed.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('counter', (value, decimals = 0, prefix = '', suffix = '') => ({
        target: Number(value) || 0,
        shown: 0,
        decimals,
        prefix,
        suffix,

        init() {
            this.roll(0, this.target);

            // $watch fires whenever Livewire hands the component a new figure.
            this.$watch('target', (now, before) => {
                this.roll(Number(before) || 0, Number(now) || 0);
                this.$el.classList.remove('settled');
                void this.$el.offsetWidth;
                this.$el.classList.add('settled');
            });
        },

        roll(from, to) {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.shown = to;
                return;
            }

            const start = performance.now();
            const span = 650;

            const step = (now) => {
                const done = Math.min((now - start) / span, 1);
                // Ease out: quick at first, gentle at the end.
                const eased = 1 - Math.pow(1 - done, 3);

                this.shown = from + (to - from) * eased;

                if (done < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        },

        get display() {
            return this.prefix + this.shown.toLocaleString(undefined, {
                minimumFractionDigits: this.decimals,
                maximumFractionDigits: this.decimals,
            }) + this.suffix;
        },
    }));

    /** Short messages that appear in the corner and fade out on their own. */
    window.Alpine.data('toasts', () => ({
        items: [],

        init() {
            window.addEventListener('toast', (event) => this.push(event.detail));

            // Livewire components announce themselves the same way. Alpine may
            // start after Livewire has already booted, so cope with both.
            const listen = () => window.Livewire.on('toast', (payload) => this.push(payload[0] ?? payload));

            if (window.Livewire) {
                listen();
            } else {
                document.addEventListener('livewire:init', listen);
            }
        },

        push(detail) {
            const id = Date.now() + Math.random();

            this.items.push({
                id,
                text: detail?.text ?? String(detail ?? ''),
                tone: detail?.tone ?? 'ok',
            });

            setTimeout(() => {
                this.items = this.items.filter((item) => item.id !== id);
            }, 4000);
        },
    }));
});

/**
 * The thin progress bar for moving between pages. Livewire fetches the next
 * page in the background, so this replaces the browser's own reload.
 */
document.addEventListener('livewire:navigate', () => {
    const bar = document.getElementById('route-progress');

    if (! bar) {
        return;
    }

    bar.style.opacity = '1';
    bar.style.width = '35%';

    requestAnimationFrame(() => { bar.style.width = '70%'; });
});

document.addEventListener('livewire:navigated', () => {
    const bar = document.getElementById('route-progress');

    if (! bar) {
        return;
    }

    bar.style.width = '100%';

    setTimeout(() => {
        bar.style.opacity = '0';
        bar.style.width = '0';
    }, 250);
});
