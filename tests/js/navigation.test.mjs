/**
 * Clicking a category must change the shelf, not the page.
 *
 * This exists because of a real bug: `data-no-skeleton` has no value, and an
 * attribute with no value reads as an empty string, which is falsy. Testing it
 * with `!` meant the flag was ignored, the whole-page outline wiped the page,
 * and the swap then found nothing to swap and fell back to a full page load —
 * exactly the reload it was written to avoid. Nothing in PHP can catch that.
 *
 * Run with: npm run test:js
 */
import { JSDOM, VirtualConsole } from 'jsdom'
import assert from 'node:assert/strict'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const js = resolve(dirname(fileURLToPath(import.meta.url)), '../../resources/js')

/** The bones of a browse page: the two swappable parts, a link, an outline. */
const PAGE = `<!DOCTYPE html><html><body>
<header>top bar</header>
<div data-page-body>
  <aside data-swap="side"><a href="/browse?category=bakery" data-swap-link data-no-skeleton>Bakery</a></aside>
  <main>
    <form action="/browse" method="GET" data-no-skeleton data-swap-form><input name="q" value=""></form>
    <div data-swap="main">Everything</div>
  </main>
</div>
<template data-skeleton="results"><div class="outline">loading</div></template>
<template data-skeleton="browse"><div>whole page</div></template>
<template data-skeleton="generic"><div>plain</div></template>
</body></html>`

const ARRIVED = PAGE.replace('>Everything<', '>SWAPPED SHELF<')

function pageWith(html) {
    const virtualConsole = new VirtualConsole()
    const state = { navigated: null, fetched: [] }

    virtualConsole.on('jsdomError', (e) => {
        if (/navigation/i.test(e.message)) {
            state.navigated = 'full page load'
        }
    })

    const dom = new JSDOM(html, { url: 'https://shop.example/browse', pretendToBeVisual: true, virtualConsole })
    const { window } = dom

    window.fetch = async (url) => {
        state.fetched.push(String(url))

        return { ok: true, status: 200, text: async () => ARRIVED }
    }
    window.scrollTo = () => {}
    window.Element.prototype.scrollIntoView = function () {}

    for (const key of ['fetch', 'DOMParser', 'HTMLFormElement', 'FormData', 'URL', 'URLSearchParams',
                       'MouseEvent', 'Element', 'Node', 'AbortController', 'history', 'location']) {
        try { globalThis[key] = window[key] } catch (error) { /* jsdom guards a few */ }
    }

    globalThis.window = window
    globalThis.document = window.document

    return { window, state }
}

const settle = (ms = 100) => new Promise((r) => setTimeout(r, ms))

const { window, state } = pageWith(PAGE)

await import(`${js}/skeleton.js`)
await import(`${js}/panel-swap.js`)

const shelf = () => window.document.querySelector('[data-swap="main"]')
const link = window.document.querySelector('a[data-swap-link]')

const click = new window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0 })
link.dispatchEvent(click)

assert.equal(click.defaultPrevented, true, 'the browser should not be left to navigate')
assert.ok(shelf(), 'the shelf must survive the click; the whole-page outline must not fire')
assert.equal(shelf().classList.contains('is-swapping'), true, 'the shelf fades rather than blanking')
assert.ok(window.document.querySelector('header'), 'the top bar stays')

await settle(300)

assert.deepEqual(state.fetched, ['https://shop.example/browse?category=bakery'], 'fetched quietly')
assert.equal(state.navigated, null, 'no full page load')
assert.match(shelf().textContent, /SWAPPED SHELF/, 'the shelf is replaced in place')
assert.equal(shelf().classList.contains('is-swapping'), false, 'the fade is taken off again')
assert.equal(window.location.search, '?category=bakery', 'the address follows along')

console.log('navigation: 9 checks passed — a category changes the shelf, not the page')
