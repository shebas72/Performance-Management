<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        :root { --brand:#0F4C5C; --brand-dark:#0A3641; --ink:#14232B; --muted:#5C6B73; --paper:#F4F7F8; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px;
               background:var(--paper); color:var(--ink);
               font-family:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",sans-serif; }
        main { width:100%; max-width:30rem; }
        h1 { margin:0 0 .5rem; font-size:2rem; line-height:1.2; color:var(--brand); }
        p { margin:0 0 1.5rem; color:var(--muted); line-height:1.6; }
        a.btn { display:inline-block; padding:.7rem 1.2rem; border-radius:6px;
                background:var(--brand); color:#fff; text-decoration:none; font-weight:500; }
        a.btn:hover { background:var(--brand-dark); }
        small { display:block; margin-top:2rem; color:var(--muted); }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>Strategy and performance management: track objectives, KPIs and initiatives in one place.</p>
        <a class="btn" href="{{ config('app.frontend_url') }}">Open the app</a>
        <small>API is running.</small>
    </main>
</body>
</html>