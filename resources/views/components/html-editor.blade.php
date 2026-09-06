@props(['state', 'value' => '', 'images' => []])

{{-- The description designer: buttons for people who do not write code, and a
     code view for people who do. --}}
<div wire:ignore x-data="htmlEditor(@js($value), @js($state))" class="rounded-lg border border-slate-300">
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-2">
        <button type="button" @click="run('bold')" class="h-8 w-8 rounded font-bold hover:bg-slate-200" title="Bold">B</button>
        <button type="button" @click="run('italic')" class="h-8 w-8 rounded italic hover:bg-slate-200" title="Italic">I</button>
        <button type="button" @click="run('underline')" class="h-8 w-8 rounded underline hover:bg-slate-200" title="Underline">U</button>

        <span class="mx-1 h-5 w-px bg-slate-300"></span>

        <button type="button" @click="block('<h2>')" class="h-8 rounded px-2 text-sm font-semibold hover:bg-slate-200">Big heading</button>
        <button type="button" @click="block('<h3>')" class="h-8 rounded px-2 text-sm font-semibold hover:bg-slate-200">Small heading</button>
        <button type="button" @click="block('<p>')" class="h-8 rounded px-2 text-sm hover:bg-slate-200">Normal text</button>

        <span class="mx-1 h-5 w-px bg-slate-300"></span>

        <button type="button" @click="run('insertUnorderedList')" class="h-8 rounded px-2 text-sm hover:bg-slate-200">• List</button>
        <button type="button" @click="run('insertOrderedList')" class="h-8 rounded px-2 text-sm hover:bg-slate-200">1. List</button>

        <span class="mx-1 h-5 w-px bg-slate-300"></span>

        <button type="button" @click="run('justifyLeft')" class="h-8 rounded px-2 text-sm hover:bg-slate-200" title="Line up on the left">Left</button>
        <button type="button" @click="run('justifyCenter')" class="h-8 rounded px-2 text-sm hover:bg-slate-200" title="Line up in the middle">Middle</button>
        <button type="button" @click="run('justifyRight')" class="h-8 rounded px-2 text-sm hover:bg-slate-200" title="Line up on the right">Right</button>

        <span class="mx-1 h-5 w-px bg-slate-300"></span>

        <button type="button" @click="link()" class="h-8 rounded px-2 text-sm hover:bg-slate-200">Link</button>

        @if (count($images) > 0)
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" class="h-8 rounded px-2 text-sm hover:bg-slate-200">Photo</button>
                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute z-10 mt-1 grid w-64 grid-cols-3 gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                    @foreach ($images as $image)
                        <button type="button" @click="insertImage(@js($image['url'])); open = false"
                                class="aspect-square overflow-hidden rounded border border-slate-200">
                            <img src="{{ $image['thumbnail'] }}" alt="{{ $image['alt'] }}" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <button type="button" @click="clean()" class="h-8 rounded px-2 text-sm hover:bg-slate-200">Clear styling</button>

        <button type="button" @click="toggleCode()"
                class="ms-auto h-8 rounded px-2 font-mono text-xs hover:bg-slate-200"
                :class="codeView ? 'bg-slate-900 text-white hover:bg-slate-800' : ''">
            &lt;/&gt; HTML
        </button>
    </div>

    <div x-ref="editor" contenteditable="true" @input="fromEditor()" @blur="fromEditor()"
         x-show="! codeView"
         class="prose-editor min-h-56 max-w-none px-4 py-3 text-sm focus:outline-none"></div>

    <textarea x-show="codeView" x-model="html" @input="fromCode()" x-cloak
              class="min-h-56 w-full border-0 px-4 py-3 font-mono text-xs focus:outline-none"
              spellcheck="false"></textarea>
</div>

<p class="mt-1 text-xs text-slate-500">
    Write it however you like. Anything that could run code is removed when you save.
</p>
