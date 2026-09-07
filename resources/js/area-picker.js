/**
 * Choosing a delivery area on a map.
 *
 * A shopkeeper searches for a place or clicks the map to drop a pin, then sets
 * how far around it they deliver. The circle on the map is the honest picture
 * of what customers will and will not see.
 *
 * Two maps are supported because the platform pays for one of them. Which one
 * a shop gets is decided by staff, and handed in here as config.provider.
 */

let leafletPromise = null
let googlePromise = null

/** Leaflet is only fetched on a page that actually shows a map. */
function loadLeaflet() {
    if (! leafletPromise) {
        leafletPromise = Promise.all([
            import('leaflet'),
            import('leaflet/dist/leaflet.css'),
        ]).then(([module]) => module.default ?? module)
    }

    return leafletPromise
}

function loadGoogle(key) {
    if (! googlePromise) {
        googlePromise = new Promise((resolve, reject) => {
            if (window.google?.maps) {
                resolve(window.google.maps)
                return
            }

            const script = document.createElement('script')
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`
            script.async = true
            script.onload = () => resolve(window.google.maps)
            script.onerror = () => reject(new Error('Google Maps did not load'))
            document.head.appendChild(script)
        })
    }

    return googlePromise
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('areaPicker', (config) => ({
        provider: config.provider || 'osm',
        lat: config.latitude ?? null,
        lng: config.longitude ?? null,
        radius: Number(config.radius) || 5,
        paths: config.paths || {},
        centre: config.centre || [23.8103, 90.4125],
        zoom: config.zoom || 11,
        googleKey: config.googleKey || '',
        searchUrl: config.searchUrl,

        query: '',
        results: [],
        searching: false,
        failed: false,
        ready: false,
        timer: null,

        map: null,
        marker: null,
        circle: null,
        lib: null,

        async init() {
            try {
                await (this.provider === 'google' ? this.startGoogle() : this.startLeaflet())
                this.ready = true
            } catch (error) {
                this.failed = true
            }

            // The radius box and the map must always agree.
            this.$watch('radius', () => {
                this.drawCircle()
                this.push('radius', this.radius)
            })
        },

        get start() {
            return this.hasPoint ? [this.lat, this.lng] : this.centre
        },

        get hasPoint() {
            return this.lat !== null && this.lng !== null && this.lat !== '' && this.lng !== ''
        },

        /* ---------------------------------------------------------- OSM */

        async startLeaflet() {
            const L = this.lib = await loadLeaflet()

            this.map = L.map(this.$refs.canvas, { scrollWheelZoom: false })
                .setView(this.start, this.hasPoint ? 13 : this.zoom)

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map)

            this.map.on('click', (event) => this.moveTo(event.latlng.lat, event.latlng.lng))

            if (this.hasPoint) {
                this.drawPin()
            }

            // The map is often built inside a panel that was hidden a moment
            // ago, and Leaflet needs telling once its box has a real size.
            setTimeout(() => this.map.invalidateSize(), 60)
        },

        /* ------------------------------------------------------- Google */

        async startGoogle() {
            const maps = this.lib = await loadGoogle(this.googleKey)

            this.map = new maps.Map(this.$refs.canvas, {
                center: { lat: this.start[0], lng: this.start[1] },
                zoom: this.hasPoint ? 13 : this.zoom,
                mapTypeControl: false,
                streetViewControl: false,
                scrollwheel: false,
            })

            this.map.addListener('click', (event) => this.moveTo(event.latLng.lat(), event.latLng.lng()))

            if (this.hasPoint) {
                this.drawPin()
            }
        },

        /* --------------------------------------------------- the pin */

        moveTo(lat, lng, zoomIn = false) {
            this.lat = Number(lat.toFixed(7))
            this.lng = Number(lng.toFixed(7))

            this.drawPin()
            this.push('latitude', this.lat)
            this.push('longitude', this.lng)

            if (zoomIn) {
                this.provider === 'google'
                    ? this.map.setCenter({ lat: this.lat, lng: this.lng })
                    : this.map.setView([this.lat, this.lng], 13)
            }
        },

        drawPin() {
            if (this.provider === 'google') {
                if (this.marker) {
                    this.marker.setPosition({ lat: this.lat, lng: this.lng })
                } else {
                    this.marker = new this.lib.Marker({
                        map: this.map,
                        position: { lat: this.lat, lng: this.lng },
                        draggable: true,
                    })

                    this.marker.addListener('dragend', (event) => {
                        this.moveTo(event.latLng.lat(), event.latLng.lng())
                    })
                }
            } else if (this.marker) {
                this.marker.setLatLng([this.lat, this.lng])
            } else {
                this.marker = this.lib.marker([this.lat, this.lng], { draggable: true }).addTo(this.map)
                this.marker.on('dragend', () => {
                    const at = this.marker.getLatLng()
                    this.moveTo(at.lat, at.lng)
                })
            }

            this.drawCircle()
        },

        drawCircle() {
            if (! this.hasPoint || ! this.map) {
                return
            }

            const metres = Math.max(Number(this.radius) || 0, 0) * 1000

            if (this.provider === 'google') {
                if (this.circle) {
                    this.circle.setCenter({ lat: this.lat, lng: this.lng })
                    this.circle.setRadius(metres)
                } else {
                    this.circle = new this.lib.Circle({
                        map: this.map,
                        center: { lat: this.lat, lng: this.lng },
                        radius: metres,
                        strokeColor: '#16a34a',
                        strokeWeight: 2,
                        fillColor: '#16a34a',
                        fillOpacity: 0.12,
                    })
                }

                return
            }

            if (this.circle) {
                this.circle.setLatLng([this.lat, this.lng])
                this.circle.setRadius(metres)
            } else {
                this.circle = this.lib.circle([this.lat, this.lng], {
                    radius: metres,
                    color: '#16a34a',
                    weight: 2,
                    fillColor: '#16a34a',
                    fillOpacity: 0.12,
                }).addTo(this.map)
            }
        },

        /* ------------------------------------------------- finding a place */

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
            this.query = result.name
            this.results = []
            this.moveTo(result.latitude, result.longitude, true)
        },

        here() {
            if (! navigator.geolocation) {
                return
            }

            navigator.geolocation.getCurrentPosition(
                (position) => this.moveTo(position.coords.latitude, position.coords.longitude, true),
                () => {},
            )
        },

        clear() {
            this.lat = null
            this.lng = null
            this.push('latitude', null)
            this.push('longitude', null)

            if (this.marker) {
                this.provider === 'google' ? this.marker.setMap(null) : this.map.removeLayer(this.marker)
                this.marker = null
            }

            if (this.circle) {
                this.provider === 'google' ? this.circle.setMap(null) : this.map.removeLayer(this.circle)
                this.circle = null
            }
        },

        /** Hand a value back to the Livewire component that owns the form. */
        push(field, value) {
            const path = this.paths[field]

            if (path && this.$wire) {
                this.$wire.set(path, value, false)
            }
        },
    }))
})
