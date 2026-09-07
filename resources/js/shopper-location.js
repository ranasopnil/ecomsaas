/**
 * The shopper saying where they are.
 *
 * A shop can limit how far it delivers, so a customer who has not said where
 * they are is shown everything and asked. Once they answer, the shop only
 * shows what can actually reach them.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('shopperLocation', (config) => ({
        searchUrl: config.searchUrl,
        open: false,
        query: '',
        results: [],
        searching: false,
        locating: false,
        timer: null,

        show() {
            this.open = true
            this.$nextTick(() => this.$refs.box?.focus())
        },

        search() {
            clearTimeout(this.timer)

            if (this.query.trim().length < 3) {
                this.results = []
                return
            }

            this.timer = setTimeout(async () => {
                this.searching = true

                try {
                    const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.query)}`, {
                        headers: { Accept: 'application/json' },
                    })

                    this.results = response.ok ? await response.json() : []
                } catch (error) {
                    this.results = []
                }

                this.searching = false
            }, 400)
        },

        pick(result) {
            this.submit(result.latitude, result.longitude, result.name)
        },

        here() {
            if (! navigator.geolocation) {
                return
            }

            this.locating = true

            navigator.geolocation.getCurrentPosition(
                (position) => this.submit(position.coords.latitude, position.coords.longitude, 'Where I am now'),
                () => { this.locating = false },
            )
        },

        /** A plain form post, so the answer survives a reload like any other page. */
        submit(latitude, longitude, label) {
            this.$refs.latitude.value = latitude
            this.$refs.longitude.value = longitude
            this.$refs.label.value = label ?? ''
            this.$refs.form.submit()
        },
    }))
})
