/**
 * The shopper saying where they are.
 *
 * A shop can limit how far it delivers, so a customer who has not said where
 * they are is shown everything and asked. Once they answer, the shop only
 * shows what can actually reach them.
 *
 * On the shop front the question is asked for them: the browser is asked for
 * the customer's position as the page opens, so most people never have to type
 * anything. A refusal is remembered, so nobody is nagged on every visit, and
 * the box is always there for anyone who wants to say somewhere else.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('shopperLocation', (config) => ({
        searchUrl: config.searchUrl,
        auto: config.auto ?? false,
        known: config.known ?? false,
        open: false,
        query: '',
        results: [],
        searching: false,
        locating: false,
        refused: false,
        timer: null,

        init() {
            if (this.auto && ! this.known && ! this.refusedBefore()) {
                this.here(true)
            }
        },

        /*
         * ------------------------------------------------ asking the browser
         */

        /** Has this browser already turned us down here? */
        refusedBefore() {
            try {
                return window.localStorage.getItem('shopper.location.refused') === '1'
            } catch (error) {
                // Private browsing can refuse storage outright.
                return false
            }
        },

        rememberRefusal() {
            try {
                window.localStorage.setItem('shopper.location.refused', '1')
            } catch (error) {
                // Nothing to do; they will simply be asked again next time.
            }
        },

        /**
         * `quiet` means we asked on our own rather than because the customer
         * clicked. Only then is a refusal worth remembering.
         */
        here(quiet = false) {
            if (! navigator.geolocation) {
                this.refused = true
                return
            }

            this.locating = true

            navigator.geolocation.getCurrentPosition(
                (position) => this.submit(position.coords.latitude, position.coords.longitude, ''),
                () => {
                    this.locating = false
                    this.refused = true

                    if (quiet) {
                        this.rememberRefusal()
                    }
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 },
            )
        },

        /*
         * ------------------------------------------------ typing an address
         */

        show() {
            this.open = true
            this.$nextTick(() => this.$refs.box?.focus())
        },

        get canDiscover() {
            return this.query.trim().length >= 3
        },

        search() {
            clearTimeout(this.timer)

            if (! this.canDiscover) {
                this.results = []
                return
            }

            this.timer = setTimeout(() => this.lookUp(), 400)
        },

        async lookUp() {
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
        },

        /** The Discover button: take the best match without making them pick. */
        async discover() {
            if (! this.canDiscover) {
                return
            }

            clearTimeout(this.timer)

            if (! this.results.length) {
                await this.lookUp()
            }

            if (this.results.length) {
                this.pick(this.results[0])
            }
        },

        pick(result) {
            this.submit(result.latitude, result.longitude, result.name)
        },

        /**
         * A plain form post, so the answer survives a reload like any other
         * page. An empty label asks the shop to name the place for us.
         */
        submit(latitude, longitude, label) {
            this.$refs.latitude.value = latitude
            this.$refs.longitude.value = longitude
            this.$refs.label.value = label ?? ''
            this.$refs.form.submit()
        },
    }))
})
