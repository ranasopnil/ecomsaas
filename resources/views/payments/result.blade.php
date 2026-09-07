@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
    $paid = $payment?->isPaid() ?? false;
    $cancelled = $payment?->status === \App\Models\Payment::STATUS_CANCELLED;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $paid ? 'Payment received' : 'Payment' }} — {{ $store->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-md px-4 py-20 text-center">
        @if ($payment === null)
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-200 text-2xl">?</div>
            <h1 class="mt-4 text-2xl font-bold">We could not find that payment</h1>
            <p class="mt-2 text-sm text-slate-500">Nothing has been charged. Please start again, or contact the shop.</p>
        @elseif ($paid)
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-700">&check;</div>
            <h1 class="mt-4 text-2xl font-bold">Payment received</h1>
            <p class="mt-2 text-sm text-slate-500">
                Thank you. {{ $store->name }} has been paid
                {{ $payment->amount->toDisplay() }} {{ $payment->amount->currency }}.
            </p>
            <dl class="mt-8 space-y-2 rounded-2xl bg-white p-5 text-start text-sm shadow-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Reference</dt>
                    <dd class="font-mono text-xs">{{ $payment->reference }}</dd>
                </div>
                @if ($payment->gateway_transaction_id)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">bKash transaction</dt>
                        <dd class="font-mono text-xs">{{ $payment->gateway_transaction_id }}</dd>
                    </div>
                @endif
            </dl>
            <p class="mt-4 text-xs text-slate-400">Keep this reference in case you need to ask about the payment.</p>
        @else
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-2xl text-rose-700">!</div>
            <h1 class="mt-4 text-2xl font-bold">{{ $cancelled ? 'Payment cancelled' : 'Payment not completed' }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ $payment->failure_reason ?: 'Nothing has been charged.' }}
            </p>
            <a href="/" class="mt-6 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                Back to the shop
            </a>
        @endif
    </main>
</body>
</html>
