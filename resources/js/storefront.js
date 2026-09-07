/**
 * The script for a shop's own pages: the front, and a product.
 *
 * The admin gets Alpine from Livewire, but a customer never loads Livewire,
 * so a shop page brings Alpine along itself. Nothing here is a cost to the
 * shopper beyond a few kilobytes: the location box, the picture switcher on a
 * product, and that is all.
 */
import Alpine from 'alpinejs'
import './shopper-location'
import './basket'
import './skeleton'

window.Alpine = Alpine
Alpine.start()
