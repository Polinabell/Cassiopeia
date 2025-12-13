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

    /* Active nav link */
    .nav-link.active {
      color: var(--cosmo-accent) !important;
      position: relative;
    }
    .nav-link.active::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 50%;
      transform: translateX(-50%);
      width: 20px;
      height: 2px;
      background: var(--cosmo-accent);
      border-radius: 2px;
    }

    /* Global animations */
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideInLeft {
      from { opacity: 0; transform: translateX(-30px); }
      to { opacity: 1; transform: translateX(0); }
    }
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }
    @keyframes shimmer {
      0% { background-position: -200% 0; }
      100% { background-position: 200% 0; }
    }

    /* Page transition */
    .container {
      animation: fadeInUp 0.4s ease-out;
    }

    /* Button hover effects */
    .btn {
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn:hover {
      transform: translateY(-2px);
    }

    /* Card animations */
    .card {
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .card:hover {
      box-shadow: 0 25px 50px rgba(0,0,0,0.35);
    }

    /* Stars animation */
    @keyframes twinkle {
      0%, 100% { opacity: 0.3; }
      50% { opacity: 1; }
    }
    body::after {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        radial-gradient(1px 1px at 10% 20%, rgba(255,255,255,0.6), transparent),
        radial-gradient(1px 1px at 30% 50%, rgba(255,255,255,0.5), transparent),
        radial-gradient(1px 1px at 50% 10%, rgba(255,255,255,0.4), transparent),
        radial-gradient(1px 1px at 70% 70%, rgba(255,255,255,0.5), transparent),
        radial-gradient(1px 1px at 85% 25%, rgba(255,255,255,0.45), transparent),
        radial-gradient(1px 1px at 95% 85%, rgba(255,255,255,0.55), transparent);
      z-index: 0;
      animation: twinkle 4s ease-in-out infinite alternate;
    }

    /* Scrollbar styling */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    ::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
    }
    ::-webkit-scrollbar-thumb {
      background: rgba(138,208,255,0.3);
      border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: rgba(138,208,255,0.5);
    }

    /* Table hover */
    .table-hover tbody tr:hover {
      background: rgba(138,208,255,0.1) !important;
    }

    /* Badge animations */
    .badge {
      transition: transform 0.2s;
    }
    .badge:hover {
      transform: scale(1.1);
    }

    /* Loading skeleton */
    .skeleton {
      background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
      background-size: 200% 100%;
      animation: shimmer 1.5s infinite;
    }
  </style>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-bs-theme="dark" class="bg-cosmo">
<nav class="navbar navbar-expand-lg mb-3">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="/dashboard">🚀 Cassiopeia</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard">📊 Dashboard</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('iss') ? 'active' : '' }}" href="/iss">🛰 ISS</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('osdr') ? 'active' : '' }}" href="/osdr">📁 OSDR</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('telemetry') ? 'active' : '' }}" href="/telemetry">📡 Телеметрия</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('jwst') ? 'active' : '' }}" href="/jwst">🔭 JWST</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('astro') ? 'active' : '' }}" href="/astro">🌠 Astro</a></li>
      </ul>
    </div>
  </div>
</nav>
@yield('content')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
