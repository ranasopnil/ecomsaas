/**
 * Putting something in the basket without losing your place.
 *
 * The + on a product is an ordinary form, so it still works with no
 * javascript at all: it posts, the page reloads, the thing is in the basket.
 * Where javascript is running this takes over, posts the same form quietly,
 * and only the number on the basket and a small note in the corner change.
 * The shopper stays exactly where they were on the page.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.store('basket', {
        count: 0,
        notes: [],
        busy: false,

        /** The count the page was rendered with. */
        start(count) {
            this.count = Number(count) || 0
        },

        async add(form) {
            if (this.busy) {
                return
            }

            this.busy = true

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                })

                // An expired page is not worth guessing at: let the form post
                // itself the ordinary way and the shopper gets a fresh one.
                if (response.status === 419) {
                    form.submit()

                    return
                }

                const answer = await response.json()

                this.count = Number(answer.count ?? this.count)

                this.note(
                    answer.ok ? `${answer.name} is in your basket` : (answer.message || 'That could not be added.'),
                    answer.ok ? 'ok' : 'no',
                )
            } catch (error) {
                this.note('Nothing happened — check your connection.', 'no')
            } finally {
                this.busy = false
            }
        },

        note(text, tone = 'ok') {
            const id = Date.now() + Math.random()

            this.notes.push({ id, text, tone })

            setTimeout(() => {
                this.notes = this.notes.filter((note) => note.id !== id)
            }, 3500)
        },

        dismiss(id) {
            this.notes = this.notes.filter((note) => note.id !== id)
        },
    })
})
