@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3 class="mb-4">🌠 Астрономические события (AstronomyAPI)</h3>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form id="astroForm" class="row g-2 align-items-end">
        <div class="col-sm-2">
          <label class="form-label small text-muted">Тело</label>
          <select name="body" class="form-select form-select-sm">
            <option value="sun">Sun</option>
            <option value="moon">Moon</option>
            <option value="mercury">Mercury</option>
            <option value="venus">Venus</option>
            <option value="mars">Mars</option>
            <option value="jupiter">Jupiter</option>
            <option value="saturn">Saturn</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Широта</label>
          <input type="text" inputmode="decimal" class="form-control form-control-sm" name="lat" value="55.7558" placeholder="-90..90">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Долгота</label>
          <input type="text" inputmode="decimal" class="form-control form-control-sm" name="lon" value="37.6176" placeholder="-180..180">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Дней</label>
          <input type="number" min="1" max="366" class="form-control form-control-sm" name="days" value="30">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Поиск</label>
          <input type="text" class="form-control form-control-sm" name="q" placeholder="фильтр">
        </div>
        <div class="col-sm-1">
          <label class="form-label small text-muted">Сортировка</label>
          <select name="sort" class="form-select form-select-sm">
            <option value="when">Дата</option>
            <option value="name">Тело</option>
            <option value="type">Событие</option>
          </select>
        </div>
        <div class="col-sm-1">
          <button class="btn btn-primary btn-sm w-100" type="submit">Показать</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Тело</th>
              <th>Событие</th>
              <th>Когда (UTC)</th>
              <th>Дополнительно</th>
            </tr>
          </thead>
          <tbody id="astroBody">
            <tr><td colspan="5" class="text-muted text-center">Загрузка...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <details class="mt-3">
    <summary class="text-muted small">Полный JSON ответ</summary>
    <pre id="astroRaw" class="bg-dark rounded p-3 small mt-2" style="max-height:400px;overflow:auto"></pre>
  </details>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('astroForm');
  const body = document.getElementById('astroBody');
  const raw = document.getElementById('astroRaw');

  function toNumber(val, min, max) {
    const num = parseFloat(String(val).replace(',', '.'));
    if (!Number.isFinite(num)) return null;
    if (typeof min === 'number' && num < min) return null;
    if (typeof max === 'number' && num > max) return null;
    return num;
  }

  async function load(q) {
    body.innerHTML = '<tr><td colspan="5" class="text-muted text-center">Загрузка...</td></tr>';

    const lat = toNumber(q.lat, -90, 90);
    const lon = toNumber(q.lon, -180, 180);
    const days = Math.max(1, Math.min(366, parseInt(q.days || '30', 10) || 30));

    if (lat === null || lon === null) {
      body.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Некорректные координаты</td></tr>';
      return;
    }

    const url = '/api/astro/events?' + new URLSearchParams({lat, lon, days, body: q.body || 'sun'}).toString();

    try {
      const r = await fetch(url);
      const js = await r.json();
      const payload = js.data ?? js;
      const data = payload.data ?? payload;
      raw.textContent = JSON.stringify(data, null, 2);

      let flat = [];
      const rows = data.rows || [];
      rows.forEach(row => {
        const bodyName = row.body?.name || row.body?.id || '—';
        (row.events || []).forEach(ev => {
          const type = ev.type || ev.event_type || ev.category || '';
          const when = ev.date || ev.time || ev.peak?.date || ev.eventHighlights?.peak?.date || '';
          const extra = ev.extraInfo?.magnitude ?? ev.extraInfo?.phase?.string ?? ev.eventHighlights?.peak?.altitude ?? '';
          flat.push({name: bodyName, type, when, extra: String(extra)});
        });
      });

      // Fallback to positions
      if (!flat.length && Array.isArray(data.positions_rows)) {
        data.positions_rows.forEach(row => {
          const bodyName = row.body?.name || row.body?.id || '—';
          (row.positions || []).forEach(p => {
            const when = p.date || '';
            const alt = p.position?.horizontal?.altitude?.string || p.position?.horizontal?.altitude?.degrees || '';
            const mag = p.extraInfo?.magnitude ?? '';
            flat.push({name: bodyName, type: 'position', when, extra: `alt ${alt}, mag ${mag}`});
          });
        });
      }

      // Search filter
      const search = (q.q || '').toLowerCase();
      if (search) {
        flat = flat.filter(r => 
          r.name.toLowerCase().includes(search) ||
          r.type.toLowerCase().includes(search) ||
          r.extra.toLowerCase().includes(search)
        );
      }

      // Sort
      const sortCol = q.sort || 'when';
      flat.sort((a, b) => {
        const va = (a[sortCol] || '').toLowerCase();
        const vb = (b[sortCol] || '').toLowerCase();
        return va > vb ? 1 : -1;
      });

      if (!flat.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-muted text-center">События не найдены</td></tr>';
        return;
      }

      body.innerHTML = flat.slice(0, 200).map((r, i) => `
        <tr style="animation-delay:${i * 0.02}s">
          <td>${i + 1}</td>
          <td>${r.name}</td>
          <td>${r.type || '—'}</td>
          <td><code>${r.when || '—'}</code></td>
          <td>${r.extra || '—'}</td>
        </tr>
      `).join('');
    } catch(e) {
      body.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Ошибка загрузки</td></tr>';
    }
  }

  form.addEventListener('submit', ev => {
    ev.preventDefault();
    const q = Object.fromEntries(new FormData(form).entries());
    load(q);
  });

  // Initial load
  load({lat: '55.7558', lon: '37.6176', days: '30', body: 'sun'});
});
</script>

<style>
  @keyframes rowFadeIn { from { opacity: 0; } to { opacity: 1; } }
  #astroBody tr { animation: rowFadeIn 0.3s ease-out backwards; }
</style>
@endsection

