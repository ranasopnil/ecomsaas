/**
 * The shape of the page that is coming.
 *
 * Between pressing a link and the next page arriving, the browser shows the
 * old page and then swaps it all at once. On a slow connection that is
 * several seconds of wondering whether the tap worked. Instead, the body of
 * the page is replaced straight away with a grey outline of what is on its
 * way — the same squares, in the same places — so the wait has a shape.
 *
 * Nothing here is required for the site to work. If this script never runs,
 * every link is still an ordinary link.
 *
 * A new page needs nothing done to it: an address nobody has named gets the
 * plain shape.
 */

/** Which shape belongs to which address. First match wins. */
const SHAPES = [
    [/\/basket$/, 'basket'],
    [/\/products\//, 'product'],
    [/\/browse/, 'browse'],
    [/\/admin\/(products|categories|brands|stock|domains|payments|couriers|orders)\/?$/, 'admin-list'],
    [/\/admin\/.+\/(new|edit)$/, 'admin-form'],
    [/\/admin\/(mail|delivery|templates|footer)/, 'admin-form'],
    [/\/super\/[a-z-]+\/(new|edit|\d+)/, 'admin-form'],
    [/\/super\//, 'admin-list'],
    [/\/admin\/?$/, 'admin-list'],
    [/^\/$/, 'home'],
]

/** How long to sit there before giving up and putting the page back. */
const PATIENCE = 20000

let showing = false
let timer = null

function shapeFor(url) {
    let path

    try {
        path = new URL(url, window.location.origin).pathname
    } catch (error) {
        return 'generic'
    }

    for (const [pattern, name] of SHAPES) {
        if (pattern.test(path)) {
            return name
        }
    }

    return 'generic'
}

function bodyElement() {
    return document.querySelector('[data-page-body]')
}

function templateFor(name) {
    return document.querySelector(`template[data-skeleton="${name}"]`)
        || document.querySelector('template[data-skeleton="generic"]')
}

export function showSkeleton(url) {
    if (showing) {
        return
    }

    const body = bodyElement()
    const template = templateFor(shapeFor(url))

    if (! body || ! template) {
        return
    }

    showing = true

    body.replaceChildren(template.content.cloneNode(true))
    body.setAttribute('aria-busy', 'true')
    window.scrollTo({ top: 0, behavior: 'instant' })

    // A page that never arrives must not leave somebody staring at grey
    // boxes for ever.
    timer = setTimeout(() => hideSkeleton(), PATIENCE)
}

/**
 * The same grey outline, but inside one part of the page rather than all of
 * it — for when only the shelf is being replaced.
 */
export function showRegionSkeleton(region, name) {
    const template = templateFor(name)

    if (! region || ! template) {
        return
    }

    region.replaceChildren(template.content.cloneNode(true))
    region.setAttribute('aria-busy', 'true')
}

export function hideSkeleton() {
    if (! showing) {
        return
    }

    showing = false
    clearTimeout(timer)

    const body = bodyElement()

    if (body) {
        body.removeAttribute('aria-busy')
    }

    // The page underneath is gone, so the only honest way back is a reload.
    // This only happens when a page failed to arrive at all.
    window.location.reload()
}

/** Is this a click we should take over from the browser? */
function isPlainNavigation(event, link) {
    return ! event.defaultPrevented
        && event.button === 0
        && ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey
        && link.target !== '_blank'
        && ! link.hasAttribute('download')
        && ! ('noSkeleton' in link.dataset)
        && link.origin === window.location.origin
        && link.getAttribute('href')
        && ! link.getAttribute('href').startsWith('#')
        && link.pathname + link.search !== window.location.pathname + window.location.search
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]')

    if (link && isPlainNavigation(event, link)) {
        showSkeleton(link.href)
    }
})

// Searching and filtering are ordinary GET forms, and take just as long.
document.addEventListener('submit', (event) => {
    const form = event.target

    if (form instanceof HTMLFormElement
        && form.method.toLowerCase() === 'get'
        && ! ('noSkeleton' in form.dataset)
        && ! event.defaultPrevented) {
        showSkeleton(form.action)
    }
})

// Coming back with the back button hands over the old page from store; it is
// already drawn, so anything left over has to go.
window.addEventListener('pageshow', (event) => {
    if (event.persisted && showing) {
        showing = false
        clearTimeout(timer)
        window.location.reload()
    }
})

/*
 * Behind the counter the page is swapped in place by Livewire rather than by
 * the browser, so it says when it starts and when it has finished.
 */
document.addEventListener('livewire:navigate', (event) => showSkeleton(event.detail?.url ?? window.location.href))

document.addEventListener('livewire:navigated', () => {
    showing = false
    clearTimeout(timer)
})
