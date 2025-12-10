<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Space Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    :root {
      --cosmo-bg: radial-gradient(120% 120% at 20% 20%, rgba(111,177,255,0.08), transparent),
                  radial-gradient(90% 90% at 80% 0%, rgba(167,108,255,0.07), transparent),
                  #0b1020;
      --cosmo-card: rgba(255,255,255,0.04);
      --cosmo-border: rgba(255,255,255,0.08);
      --cosmo-accent: #8ad0ff;
    }
    body {
      font-family: 'Space Grotesk', system-ui, -apple-system, sans-serif;
      background: var(--cosmo-bg);
      color: #e8edf7;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
    }
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        radial-gradient(1px 1px at 20% 30%, rgba(255,255,255,0.4), transparent),
        radial-gradient(1px 1px at 70% 10%, rgba(255,255,255,0.5), transparent),
        radial-gradient(1px 1px at 40% 80%, rgba(255,255,255,0.4), transparent),
        radial-gradient(1px 1px at 90% 60%, rgba(255,255,255,0.35), transparent);
      z-index: 0;
      opacity: .6;
    }
    a, .nav-link, .navbar-brand { color: #e8edf7; }
    a:hover, .nav-link:hover, .navbar-brand:hover { color: var(--cosmo-accent); }
    .navbar {
      background: rgba(255,255,255,0.04) !important;
      backdrop-filter: blur(10px);
      border-bottom: 1px solid var(--cosmo-border);
      box-shadow: 0 10px 40px rgba(0,0,0,0.25);
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .card {
      background: var(--cosmo-card);
      border: 1px solid var(--cosmo-border);
      color: #e8edf7;
      box-shadow: 0 20px 40px rgba(0,0,0,0.25);
      backdrop-filter: blur(12px);
    }
    .card-title { color: #f7fbff; }
    .table { color: #dce5f5; }
    .table thead th { border-color: var(--cosmo-border); color: #f3f7ff; }
    .table td, .table th { border-color: var(--cosmo-border); }
    .btn-primary {
      background: linear-gradient(135deg, #6fb1ff, #a06bff);
      border: none;
      box-shadow: 0 10px 30px rgba(111,177,255,0.35);
    }
    .btn-primary:hover { filter: brightness(1.05); }
    .form-control, .form-select {
      background: rgba(255,255,255,0.04);
      color: #e8edf7;
      border: 1px solid var(--cosmo-border);
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--cosmo-accent);
      box-shadow: 0 0 0 .2rem rgba(138,208,255,0.25);
    }
    #map { height: 340px; border-radius: 12px; }
  </style>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-bs-theme="dark" class="bg-cosmo">
<nav class="navbar navbar-expand-lg mb-3">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="/dashboard">🚀 Cassiopeia</a>
    <div class="d-flex gap-3">
      <a class="nav-link" href="/dashboard">Dashboard</a>
      <a class="nav-link" href="/iss" onclick="location.href='/dashboard';return false;">ISS</a>
      <a class="nav-link" href="/osdr">OSDR</a>
    </div>
  </div>
</nav>
@yield('content')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
