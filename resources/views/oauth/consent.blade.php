<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'IBM Plex Sans', system-ui, sans-serif; background: #f4f1ec; color: #1a1a1a; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 400px; width: 100%; background: #fff; border: 1px solid #e0dbd3; border-radius: 12px; padding: 24px; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { font-size: 0.875rem; color: #4a4a4a; margin: 0 0 16px; line-height: 1.5; }
        code { background: #f4f1ec; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; }
        .actions { display: flex; gap: 12px; }
        a.btn { flex: 1; text-align: center; font-size: 0.875rem; font-weight: 500; padding: 10px 16px; border-radius: 8px; text-decoration: none; }
        a.allow { background: #1a1a1a; color: #fff; }
        a.cancel { border: 1px solid #e0dbd3; color: #4a4a4a; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Connect to {{ config('app.name') }}</h1>
        <p>An AI client is requesting access to view your prospect scans and start single-site audits on your behalf.</p>
        <p>Scope: <code>{{ $scope }}</code></p>
        <div class="actions">
            <a href="{{ $authorizeUrl }}" class="btn allow">Allow</a>
            <a href="{{ route('search.index') }}" class="btn cancel">Cancel</a>
        </div>
    </div>
</body>
</html>
