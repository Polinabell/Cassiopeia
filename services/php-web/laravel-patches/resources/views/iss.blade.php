@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3 class="mb-4">🛰 Международная космическая станция</h3>

  <div class="row g-4">
    {{-- Left: Map --}}
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>Текущее положение</strong>
          <button class="btn btn-sm btn-outline-light" onclick="centerMap()">📍 Центрировать</button>
        </div>
        <div class="card-body p-0">
          <div id="map" style="height:400px;border-radius:0 0 8px 8px"></div>
        </div>
      </div>
    </div>

    {{-- Right: Stats --}}
    <div class="col-lg-5">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><strong>📊 Текущие показатели</strong></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="stat-box">
                <div class="stat-label text-muted small">Широта</div>
                <div class="stat-value" id="lat">—</div>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-box">
                <div class="stat-label text-muted small">Долгота</div>
                <div class="stat-value" id="lon">—</div>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-box">
                <div class="stat-label text-muted small">Высота (км)</div>
                <div class="stat-value" id="alt">—</div>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-box">
                <div class="stat-label text-muted small">Скорость (км/ч)</div>
                <div class="stat-value" id="vel">—</div>
              </div>
            </div>
          </div>
          <div class="mt-3 text-muted small">
            Обновлено: <code id="lastUpdate">—</code>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>📈 Тренд движения</strong>
          <div>
            <select id="trendLimit" class="form-select form-select-sm" style="width:auto;display:inline">
              <option value="50">50 точек</option>
              <option value="100">100 точек</option>
              <option value="240" selected>240 точек</option>
            </select>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-6"><canvas id="speedChart" height="120"></canvas></div>
            <div class="col-6"><canvas id="altChart" height="120"></canvas></div>
          </div>
          <div class="mt-2 small">
            <span class="text-muted">Δ перемещение:</span> <strong id="deltaKm">—</strong> км
          </div>
        </div>
      </div>
    </div>

    {{-- Trend Table --}}
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <strong>📋 История позиций</strong>
          <div class="d-flex gap-2">
            <input type="text" id="trendSearch" class="form-control form-control-sm" placeholder="Поиск..." style="width:150px">
            <select id="trendSort" class="form-select form-select-sm" style="width:auto">
              <option value="at">По времени</option>
              <option value="velocity">По скорости</option>
              <option value="altitude">По высоте</option>
            </select>
            <select id="trendDir" class="form-select form-select-sm" style="width:auto">
              <option value="desc">↓ DESC</option>
              <option value="asc">↑ ASC</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:300px">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-dark sticky-top">
                <tr>
                  <th>#</th>
                  <th>Время (UTC)</th>
                  <th>Широта</th>
                  <th>Долгота</th>
                  <th>Высота (км)</th>
                  <th>Скорость (км/ч)</th>
                </tr>
              </thead>
              <tbody id="trendBody">
                <tr><td colspan="6" class="text-muted text-center">Загрузка...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .stat-box {
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
  }
  .stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--cosmo-accent);
  }
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
  }
  .updating { animation: pulse 1s infinite; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  let map, marker, trail;
  let trendData = [];

  // Init Map
  if (typeof L !== 'undefined') {
    map = L.map('map', { attributionControl: false }).setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    marker = L.marker([0, 0]).addTo(map).bindPopup('МКС');
    trail = L.polyline([], { color: '#8ad0ff', weight: 2 }).addTo(map);
  }

  // Init Charts
  const speedChart = new Chart(document.getElementById('speedChart'), {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Скорость', data: [], borderColor: '#8ad0ff', tension: 0.3 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { display: false } } }
  });
  const altChart = new Chart(document.getElementById('altChart'), {
    type: 'line',
    data: { labels: [], datasets: [{ label: 'Высота', data: [], borderColor: '#a06bff', tension: 0.3 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { x: { display: false } } }
  });

  window.centerMap = () => {
    if (map && marker) {
      map.setView(marker.getLatLng(), 4);
    }
  };

  async function loadLast() {
    try {
      const r = await fetch('/api/iss/last');
      const js = await r.json();
      const data = js.data ?? js;
      const p = data.payload ?? data;

      document.getElementById('lat').textContent = p.latitude?.toFixed(4) ?? '—';
      document.getElementById('lon').textContent = p.longitude?.toFixed(4) ?? '—';
      document.getElementById('alt').textContent = p.altitude?.toFixed(2) ?? '—';
      document.getElementById('vel').textContent = p.velocity?.toFixed(0) ?? '—';
      document.getElementById('lastUpdate').textContent = data.fetched_at ?? new Date().toISOString();

      if (map && p.latitude && p.longitude) {
        marker.setLatLng([p.latitude, p.longitude]);
      }
    } catch(e) {}
  }

  async function loadTrend() {
    const limit = document.getElementById('trendLimit').value;
    try {
      const r = await fetch(`/api/iss/trend?limit=${limit}`);
      const js = await r.json();
      const data = js.data ?? js;
      trendData = data.points || [];

      // Update charts
      const labels = trendData.map(p => new Date(p.at).toLocaleTimeString());
      speedChart.data.labels = labels;
      speedChart.data.datasets[0].data = trendData.map(p => p.velocity);
      speedChart.update();

      altChart.data.labels = labels;
      altChart.data.datasets[0].data = trendData.map(p => p.altitude);
      altChart.update();

      // Update trail
      if (trail) {
        trail.setLatLngs(trendData.map(p => [p.lat, p.lon]));
      }

      // Delta
      document.getElementById('deltaKm').textContent = (data.delta_km ?? 0).toFixed(2);

      renderTrendTable();
    } catch(e) {}
  }

  function renderTrendTable() {
    const body = document.getElementById('trendBody');
    const search = document.getElementById('trendSearch').value.toLowerCase();
    const sortCol = document.getElementById('trendSort').value;
    const sortDir = document.getElementById('trendDir').value;

    let filtered = [...trendData];

    // Filter
    if (search) {
      filtered = filtered.filter(p => 
        String(p.at).toLowerCase().includes(search) ||
        String(p.lat).includes(search) ||
        String(p.lon).includes(search)
      );
    }

    // Sort
    filtered.sort((a, b) => {
      const va = a[sortCol] ?? 0;
      const vb = b[sortCol] ?? 0;
      return sortDir === 'asc' ? (va - vb) : (vb - va);
    });

    if (!filtered.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Нет данных</td></tr>';
      return;
    }

    body.innerHTML = filtered.slice(0, 100).map((p, i) => `
      <tr style="animation: fadeIn 0.2s ease-out backwards; animation-delay: ${i * 0.02}s">
        <td>${i + 1}</td>
        <td><code>${new Date(p.at).toISOString().replace('T', ' ').substring(0, 19)}</code></td>
        <td>${p.lat?.toFixed(4) ?? '—'}</td>
        <td>${p.lon?.toFixed(4) ?? '—'}</td>
        <td>${p.altitude?.toFixed(2) ?? '—'}</td>
        <td>${p.velocity?.toFixed(0) ?? '—'}</td>
      </tr>
    `).join('');
  }

  // Event listeners
  document.getElementById('trendLimit').addEventListener('change', loadTrend);
  document.getElementById('trendSearch').addEventListener('input', renderTrendTable);
  document.getElementById('trendSort').addEventListener('change', renderTrendTable);
  document.getElementById('trendDir').addEventListener('change', renderTrendTable);

  // Initial load
  loadLast();
  loadTrend();

  // Auto-refresh
  setInterval(loadLast, 15000);
  setInterval(loadTrend, 60000);
});
</script>

<style>
  @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
@endsection
