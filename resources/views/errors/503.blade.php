<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Mise à jour en cours — {{ config('app.name') }}</title>
    <style>
        {{-- Prerendered to static HTML by `artisan down --render`, so it must
             not depend on the site's own (possibly mid-rebuild) Vite assets. --}}
        html, body {
            height: 100%;
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f0fdf4;
            color: #064e3b;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            max-width: 28rem;
            text-align: center;
        }
        .icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1.25rem;
            border-radius: 9999px;
            background: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            width: 1.5rem;
            height: 1.5rem;
            stroke: #ffffff;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
        }
        p {
            margin: 0;
            color: #047857;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 6v6l4 2" />
                <circle cx="12" cy="12" r="10" />
            </svg>
        </div>
        <h1>Mise à jour en cours</h1>
        <p>
            {{ config('app.name') }} applique une mise à jour et sera de retour dans quelques instants.
            Cette page se rechargera automatiquement.
        </p>
    </div>
    <script>
        setTimeout(() => window.location.reload(), {{ ((int) ($retryAfter ?? 60)) * 1000 }});
    </script>
</body>
</html>
