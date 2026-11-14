<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No se pudo completar la solicitud</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #1e293b; font: 1rem/1.55 system-ui, sans-serif; }
        main { width: min(30rem, calc(100% - 5rem)); padding: 2rem; background: white; border: 1px solid #e2e8f0; border-radius: 1rem; }
        h1 { font-size: 1.55rem; line-height: 1.25; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
        a { padding: .55rem .85rem; border: 1px solid #881337; border-radius: .5rem; color: #881337; text-decoration: none; }
        .primary { color: white; background: #881337; }
    </style>
</head>
<body>
    <main>
        <h1>No se pudo completar la solicitud</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            @if (request()->isMethod('GET'))
                <a class="primary" href="{{ url()->full() }}">Actualizar página</a>
            @endif
            <a href="{{ url('/') }}">Ir al inicio</a>
        </div>
        <small>Código {{ $status }}</small>
    </main>
</body>
</html>
