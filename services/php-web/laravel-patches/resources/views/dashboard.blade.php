@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="text-center mb-5">
    <h1 class="display-5 fw-bold mb-3 hero-title">🚀 Space Dashboard</h1>
    <p class="lead text-muted">Централизованная панель мониторинга космических данных</p>
  </div>

  {{-- Quick Stats --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100">
        <div class="card-body text-center">
          <div class="stat-icon">🛰</div>
          <div class="stat-value" id="issSpeed">—</div>
          <div class="stat-label text-muted small">Скорость МКС, км/ч</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100">
        <div class="card-body text-center">
          <div class="stat-icon">📍</div>
          <div class="stat-value" id="issAlt">—</div>
          <div class="stat-label text-muted small">Высота МКС, км</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100">
        <div class="card-body text-center">
          <div class="stat-icon">📡</div>
          <div class="stat-value" id="telemetryCount">{{ count($telemetry ?? []) }}</div>
          <div class="stat-label text-muted small">Записей телеметрии</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card stat-card h-100">
        <div class="card-body text-center">
          <div class="stat-icon">⏱</div>
          <div class="stat-value" id="uptime">{{ date('H:i') }}</div>
          <div class="stat-label text-muted small">Время (UTC)</div>
          </div>
        </div>
      </div>
    </div>

  {{-- Main Navigation Cards --}}
  <div class="row g-4">
    <div class="col-md-6 col-lg-4">
      <a href="/iss" class="text-decoration-none">
        <div class="card nav-card h-100">
        <div class="card-body">
            <div class="nav-card-icon">🛰</div>
            <h5 class="card-title">МКС Трекер</h5>
            <p class="card-text text-muted small">Положение и траектория Международной космической станции в реальном времени</p>
              </div>
          <div class="card-footer bg-transparent border-0">
            <span class="badge bg-success">Live</span>
              </div>
              </div>
      </a>
              </div>

    <div class="col-md-6 col-lg-4">
      <a href="/telemetry" class="text-decoration-none">
        <div class="card nav-card h-100">
          <div class="card-body">
            <div class="nav-card-icon">📡</div>
            <h5 class="card-title">Телеметрия</h5>
            <p class="card-text text-muted small">Данные датчиков с сортировкой, фильтрацией и экспортом в CSV/XLSX</p>
              </div>
          <div class="card-footer bg-transparent border-0">
            <span class="badge bg-primary">Export</span>
          </div>
        </div>
      </a>
          </div>

    <div class="col-md-6 col-lg-4">
      <a href="/osdr" class="text-decoration-none">
        <div class="card nav-card h-100">
          <div class="card-body">
            <div class="nav-card-icon">📁</div>
            <h5 class="card-title">NASA OSDR</h5>
            <p class="card-text text-muted small">Open Science Data Repository — научные датасеты NASA</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <span class="badge bg-info">Datasets</span>
          </div>
        </div>
      </a>
    </div>


    <div class="col-md-6 col-lg-4">
      <a href="/jwst" class="text-decoration-none">
        <div class="card nav-card h-100">
          <div class="card-body">
            <div class="nav-card-icon">🔭</div>
            <h5 class="card-title">JWST Галерея</h5>
            <p class="card-text text-muted small">Изображения телескопа James Webb с фильтрацией по инструментам</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <span class="badge bg-secondary">Gallery</span>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-6 col-lg-4">
      <a href="/astro" class="text-decoration-none">
        <div class="card nav-card h-100">
          <div class="card-body">
            <div class="nav-card-icon">🌠</div>
            <h5 class="card-title">Астрономия</h5>
            <p class="card-text text-muted small">События и позиции небесных тел (AstronomyAPI)</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <span class="badge bg-danger">Events</span>
        </div>
      </div>
      </a>
  </div>
</div>

  {{-- Mini Map & Telemetry Preview --}}
  <div class="row g-4 mt-3">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>🗺 МКС на карте</strong>
          <a href="/iss" class="btn btn-sm btn-outline-light">Подробнее</a>
              </div>
        <div class="card-body p-0">
          <div id="map" style="height:280px;border-radius:0 0 8px 8px"></div>
              </div>
              </div>
          </div>

    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>📊 Последняя телеметрия</strong>
          <a href="/telemetry" class="btn btn-sm btn-outline-light">Все данные</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:280px;overflow:auto">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-dark sticky-top">
                <tr><th>Время</th><th>V</th><th>T°</th><th>Файл</th></tr>
              </thead>
              <tbody>
                @forelse(($telemetry ?? []) as $row)
                  <tr>
                    <td><code class="small">{{ substr($row['recorded_at'], 11, 8) }}</code></td>
                    <td>{{ number_format($row['voltage'], 1) }}</td>
                    <td>{{ number_format($row['temp'], 1) }}</td>
                    <td class="small text-truncate" style="max-width:100px">{{ $row['source_file'] }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-muted text-center">нет данных</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
  </div>
</div>

  {{-- CMS Block --}}
  @if(!empty($cms_block))
  <div class="card mt-4">
    <div class="card-header fw-semibold">📝 Информация</div>
    <div class="card-body">{!! $cms_block !!}</div>
  </div>
  @endif
</div>

<style>
  .hero-title {
    background: linear-gradient(135deg, #8ad0ff, #a06bff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    animation: heroGlow 3s ease-in-out infinite;
  }
  @keyframes heroGlow {
    0%, 100% { filter: brightness(1); }
    50% { filter: brightness(1.2); }
  }

  .stat-card {
    transition: transform 0.3s, box-shadow 0.3s;
    animation: statFadeIn 0.5s ease-out backwards;
  }
  .stat-card:nth-child(1) { animation-delay: 0s; }
  .stat-card:nth-child(2) { animation-delay: 0.1s; }
  .stat-card:nth-child(3) { animation-delay: 0.2s; }
  .stat-card:nth-child(4) { animation-delay: 0.3s; }
  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(138,208,255,0.2);
  }
  .stat-icon { font-size: 2rem; margin-bottom: 0.5rem; }
  .stat-value { font-size: 1.75rem; font-weight: 600; color: var(--cosmo-accent); }

  @keyframes statFadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .nav-card {
    transition: transform 0.3s, box-shadow 0.3s;
    cursor: pointer;
    animation: navCardIn 0.6s ease-out backwards;
  }
  .nav-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 50px rgba(138,208,255,0.25);
  }
  .nav-card-icon { font-size: 2.5rem; margin-bottom: 1rem; }

  @for($i = 0; $i < 6; $i++)
    .col-md-6:nth-child({{ $i + 1 }}) .nav-card { animation-delay: {{ 0.1 + $i * 0.1 }}s; }
  @endfor

  @keyframes navCardIn {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  // Update time
  setInterval(() => {
    const now = new Date();
    document.getElementById('uptime').textContent = 
      now.getUTCHours().toString().padStart(2,'0') + ':' + 
      now.getUTCMinutes().toString().padStart(2,'0');
  }, 1000);

  // Load ISS data
  try {
    const r = await fetch('/api/iss/last');
    const js = await r.json();
    const data = js.data ?? js;
    const p = data.payload ?? data;
    if (p.velocity) document.getElementById('issSpeed').textContent = Math.round(p.velocity).toLocaleString();
    if (p.altitude) document.getElementById('issAlt').textContent = Math.round(p.altitude).toLocaleString();
  } catch(e) {}

  // Mini Map
  if (typeof L !== 'undefined') {
    const map = L.map('map', { attributionControl: false }).setView([0, 0], 1);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    const marker = L.marker([0, 0]).addTo(map).bindPopup('МКС');

    async function updateMap() {
      try {
        const r = await fetch('/api/iss/last');
        const js = await r.json();
        const data = js.data ?? js;
        const p = data.payload ?? data;
        if (p.latitude && p.longitude) {
          const lat = parseFloat(p.latitude);
          const lon = parseFloat(p.longitude);
          marker.setLatLng([lat, lon]);
          map.setView([lat, lon], 3);
        }
      } catch(e) {}
    }
    updateMap();
    setInterval(updateMap, 30000);
  }
});
</script>
@endsection
