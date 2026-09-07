/**
 * One product, with its choices.
 *
 * A product that comes in sizes and colours is really several things for sale
 * behind one name. Picking a size and a colour picks one of them, and the
 * price, the stock, the photograph and the total all follow that choice
 * without the page being sent again.
 *
 * Nothing here decides what anything costs. Every price shown comes from the
 * shop, and the shop works the bill out again when the order is placed.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('productPage', (config) => ({
        variants: config.variants || [],
        options: config.options || [],
        symbol: config.symbol || '',
        exponent: Number(config.exponent) || 0,
        max: Number(config.max) || 99,

        /** option id => chosen value id */
        chosen: {},
        quantity: 1,
        shown: config.image || null,
        showingVideo: false,

        init() {
            // Start on something that can actually be bought, so nobody lands
            // on "sold out" when the shop has plenty of the other colour.
            const opening = this.variants.find((v) => v.sellable) || this.variants[0]

            if (opening) {
                this.options.forEach((option) => {
                    const match = option.values.find((value) => opening.values.includes(value.id))
                    this.chosen[option.id] = match ? match.id : option.values[0]?.id
                })
            }

            this.showPhotoFor(this.variant)
        },

        get variant() {
            const wanted = Object.values(this.chosen)

            return this.variants.find((v) => wanted.every((id) => v.values.includes(id)))
                ?? (this.variants.length === 1 ? this.variants[0] : null)
        },

        /** Is there any variant at all with this value in it that can be sold? */
        available(optionId, valueId) {
            const others = Object.entries(this.chosen)
                .filter(([id]) => Number(id) !== Number(optionId))
                .map(([, value]) => value)

            return this.variants.some((v) => v.sellable
                && v.values.includes(valueId)
                && others.every((id) => v.values.includes(id)))
        },

        choose(optionId, valueId) {
            this.chosen[optionId] = valueId
            this.quantity = 1
            this.showPhotoFor(this.variant)
        },

        showPhotoFor(variant) {
            if (variant?.image) {
                this.shown = variant.image
                this.showingVideo = false
            }
        },

        show(url) {
            this.shown = url
            this.showingVideo = false
        },

        playVideo() {
            this.showingVideo = true
        },

        get sellable() {
            return !! this.variant?.sellable
        },

        get inStock() {
            return this.variant?.stock ?? 0
        },

        get ceiling() {
            const stock = this.variant?.tracked ? this.inStock : this.max

            return Math.max(1, Math.min(this.max, stock || this.max))
        },

        less() {
            this.quantity = Math.max(1, this.quantity - 1)
        },

        more() {
            this.quantity = Math.min(this.ceiling, this.quantity + 1)
        },

        get totalMinor() {
            return (this.variant?.price ?? 0) * this.quantity
        },

        money(minor) {
            return this.symbol + new Intl.NumberFormat(undefined, {
                minimumFractionDigits: this.exponent,
                maximumFractionDigits: this.exponent,
            }).format(minor / Math.pow(10, this.exponent))
        },
    }))
})
