<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f6f7f9; color: #16181d; }
        main { max-width: 40rem; margin: 18vh auto; padding: 2rem; text-align: center; }
        h1 { font-size: 2rem; margin: 0 0 .5rem; }
        p { color: #5c6370; margin: 0; }
        code { background: #e9ebef; padding: .15rem .4rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $store->name }}</h1>
        <p>This store is live. Its shopfront is being built.</p>
        <p style="margin-top:1.5rem"><code>{{ request()->getHost() }}</code></p>
    </main>
</body>
</html>
