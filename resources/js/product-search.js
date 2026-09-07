/**
 * The shop's own search box, with suggestions as you type.
 *
 * The box is an ordinary form: typing something and pressing the button
 * searches the shop, with or without javascript. Where javascript is running,
 * a few letters bring up a short list of matching things with their pictures
 * and prices, and picking one goes straight to it.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('productSearch', (config) => ({
        url: config.url,
        query: config.query || '',
        results: [],
        total: 0,
        open: false,
        searching: false,
        highlighted: -1,
        timer: null,
        lastAsked: '',

        get canSearch() {
            return this.query.trim().length >= 2
        },

        look() {
            clearTimeout(this.timer)
            this.highlighted = -1

            if (! this.canSearch) {
                this.results = []
                this.total = 0
                this.open = false

                return
            }

            this.timer = setTimeout(() => this.ask(), 220)
        },

        async ask() {
            const asked = this.query.trim()

            this.searching = true

            try {
                const response = await fetch(`${this.url}?q=${encodeURIComponent(asked)}`, {
                    headers: { Accept: 'application/json' },
                })

                const answer = response.ok ? await response.json() : { results: [], total: 0 }

                // A slower answer to an older question must not overwrite a
                // newer one.
                if (asked !== this.query.trim()) {
                    return
                }

                this.results = answer.results ?? []
                this.total = answer.total ?? 0
                this.lastAsked = asked
                this.open = true
            } catch (error) {
                this.results = []
                this.total = 0
            } finally {
                this.searching = false
            }
        },

        /** Opening again after a click away, without asking twice. */
        reopen() {
            if (this.results.length && this.query.trim() === this.lastAsked) {
                this.open = true
            } else {
                this.look()
            }
        },

        move(step) {
            if (! this.open || ! this.results.length) {
                return
            }

            const last = this.results.length - 1
            this.highlighted += step

            if (this.highlighted > last) {
                this.highlighted = -1
            } else if (this.highlighted < -1) {
                this.highlighted = last
            }
        },

        /** Enter takes the highlighted thing, or searches the whole shop. */
        choose(event) {
            if (this.highlighted >= 0 && this.results[this.highlighted]) {
                event.preventDefault()
                window.location.href = this.results[this.highlighted].url
            }
        },

        close() {
            this.open = false
            this.highlighted = -1
        },
    }))
})
