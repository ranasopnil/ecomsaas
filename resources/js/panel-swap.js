/**
 * Changing the shelf without changing the page.
 *
 * Picking a category used to fetch a whole new page: the top bar, the basket,
 * the search box and the shelf all thrown away and drawn again, and the view
 * jumping back to the top. Only the shelf and the list of categories actually
 * differ, so only those are replaced now.
 *
 * The server still answers with the whole page, which is what somebody with no
 * javascript gets. This takes the two parts it needs out of that answer, so
 * there is one description of the page rather than two that can drift apart.
 */
import { showRegionSkeleton } from './skeleton'

/** The parts of the page that change when a category is picked. */
const REGIONS = '[data-swap]'

let current = null

function regionsIn(root) {
    const found = {}

    root.querySelectorAll(REGIONS).forEach((element) => {
        found[element.dataset.swap] = element
    })

    return found
}

/**
 * The search box lives outside the swapped parts so that what somebody has
 * typed is not thrown away underneath them. Its hidden category has to keep
 * up with where they now are.
 */
function realignSearch(url) {
    const form = document.querySelector('form[action*="/browse"] input[name="q"]')?.form

    if (! form) {
        return
    }

    const category = new URL(url, window.location.origin).searchParams.get('category')
    let field = form.querySelector('input[name="category"]')

    if (category) {
        if (! field) {
            field = document.createElement('input')
            field.type = 'hidden'
            field.name = 'category'
            form.appendChild(field)
        }

        field.value = category
    } else if (field) {
        field.remove()
    }
}

export async function swapTo(url, { push = true } = {}) {
    const here = regionsIn(document)

    if (! Object.keys(here).length) {
        window.location.href = url

        return
    }

    // A second click while the first is still in the air wins.
    current?.abort()
    current = new AbortController()

    showRegionSkeleton(here.main, 'results')

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: current.signal,
        })

        if (! response.ok) {
            throw new Error('not ok')
        }

        const arrived = new DOMParser().parseFromString(await response.text(), 'text/html')
        const theirs = regionsIn(arrived)

        // An answer with nothing to swap is not the page we expected.
        if (! Object.keys(theirs).length) {
            throw new Error('nothing to swap')
        }

        Object.entries(theirs).forEach(([name, element]) => {
            if (here[name]) {
                here[name].replaceChildren(...element.childNodes)
                here[name].removeAttribute('aria-busy')
            }
        })

        document.title = arrived.title || document.title
        realignSearch(url)

        if (push) {
            window.history.pushState({ swap: true }, '', url)
        }

        // The shelf, not the top of the page: the categories stay where the
        // eye left them.
        here.main?.scrollIntoView({ block: 'start', behavior: 'instant' })
    } catch (error) {
        if (error.name === 'AbortError') {
            return
        }

        // Anything unexpected hands back to the browser, which can always do it.
        window.location.href = url
    } finally {
        current = null
    }
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-swap-link]')

    if (! link
        || event.defaultPrevented
        || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
        || link.target === '_blank') {
        return
    }

    event.preventDefault()
    swapTo(link.href)
})

// Searching narrows the same shelf, so it swaps the same way.
document.addEventListener('submit', (event) => {
    const form = event.target

    if (! (form instanceof HTMLFormElement)
        || event.defaultPrevented
        || ! form.dataset.swapForm
        || ! document.querySelector('[data-swap="main"]')) {
        return
    }

    event.preventDefault()

    const url = new URL(form.action, window.location.origin)
    url.search = new URLSearchParams(new FormData(form)).toString()

    swapTo(url.toString())
})

// Back and forward have to work like any other page.
window.addEventListener('popstate', (event) => {
    if (event.state?.swap && document.querySelector('[data-swap="main"]')) {
        swapTo(window.location.href, { push: false })
    }
})
