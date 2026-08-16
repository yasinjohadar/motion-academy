<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/images/brand-logos/favicon.ico') }}" type="image/x-icon">

    {{--
        Styles are inlined rather than linked. An error page is exactly the moment the
        rest of the stack may be unavailable — a missing Vite manifest, an unreachable
        asset host, a half-finished deploy — and a 500 page that itself fails to render
        tells the visitor nothing at all.
    --}}
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --paper: #f0f2f5;
            --panel: #ffffff;
            --line: rgba(15, 23, 42, 0.08);
            --accent: #0d6efd;
            --accent-soft: rgba(13, 110, 253, 0.1);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #e2e8f0;
                --muted: #94a3b8;
                --paper: #0f172a;
                --panel: #1e293b;
                --line: rgba(255, 255, 255, 0.08);
                --accent-soft: rgba(13, 110, 253, 0.18);
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--paper);
            color: var(--ink);
            font-family: 'Cairo', 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
            line-height: 1.7;
        }

        .card {
            width: 100%;
            max-width: 30rem;
            padding: 2.5rem 2rem;
            text-align: center;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 1.15rem;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06);
        }

        .code {
            display: inline-block;
            padding: 0.35rem 1rem;
            margin-bottom: 1.25rem;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            /* The number reads left-to-right even inside an RTL page. */
            direction: ltr;
        }

        h1 {
            margin: 0 0 0.6rem;
            font-size: 1.4rem;
            font-weight: 700;
        }

        p {
            margin: 0 0 1.75rem;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .actions {
            display: flex;
            gap: 0.6rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        a.btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.6rem 1.35rem;
            border-radius: 0.7rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--line);
            color: var(--ink);
            transition: border-color 0.2s ease, color 0.2s ease;
        }

        a.btn:hover { border-color: var(--accent); color: var(--accent); }

        a.btn-primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        a.btn-primary:hover { opacity: 0.9; color: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <span class="code">@yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">العودة للرئيسية</a>
            @auth
                <a class="btn" href="{{ route('admin.dashboard') }}">لوحة التحكم</a>
            @endauth
        </div>
    </div>
</body>
</html>
