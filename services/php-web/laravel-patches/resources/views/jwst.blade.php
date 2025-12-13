@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3 class="mb-4">🔭 James Webb Space Telescope — Галерея</h3>

  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form id="jwstFilter" class="row g-2 align-items-end">
        <div class="col-sm-2">
          <label class="form-label small text-muted">Источник</label>
          <select class="form-select form-select-sm" name="source" id="srcSel">
            <option value="jpg" {{ ($filter['source'] ?? '') === 'jpg' ? 'selected' : '' }}>Все JPG</option>
            <option value="suffix" {{ ($filter['source'] ?? '') === 'suffix' ? 'selected' : '' }}>По суффиксу</option>
            <option value="program" {{ ($filter['source'] ?? '') === 'program' ? 'selected' : '' }}>По программе</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Суффикс</label>
          <input type="text" class="form-control form-control-sm" name="suffix" id="suffixInp" placeholder="_cal / _thumb" value="{{ $filter['suffix'] ?? '' }}">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Программа</label>
          <input type="text" class="form-control form-control-sm" name="program" id="progInp" placeholder="2734" value="{{ $filter['program'] ?? '' }}">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Инструмент</label>
          <select class="form-select form-select-sm" name="instrument">
            <option value="">Любой</option>
            <option {{ ($filter['instrument'] ?? '') === 'NIRCam' ? 'selected' : '' }}>NIRCam</option>
            <option {{ ($filter['instrument'] ?? '') === 'MIRI' ? 'selected' : '' }}>MIRI</option>
            <option {{ ($filter['instrument'] ?? '') === 'NIRISS' ? 'selected' : '' }}>NIRISS</option>
            <option {{ ($filter['instrument'] ?? '') === 'NIRSpec' ? 'selected' : '' }}>NIRSpec</option>
            <option {{ ($filter['instrument'] ?? '') === 'FGS' ? 'selected' : '' }}>FGS</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Поиск</label>
          <input type="text" class="form-control form-control-sm" name="q" placeholder="Ключевое слово">
        </div>
        <div class="col-sm-1">
          <label class="form-label small text-muted">Кол-во</label>
          <select class="form-select form-select-sm" name="perPage">
            <option>12</option>
            <option {{ ($filter['perPage'] ?? 24) == 24 ? 'selected' : '' }}>24</option>
            <option>36</option>
            <option>48</option>
          </select>
        </div>
        <div class="col-sm-1">
          <button class="btn btn-primary btn-sm w-100" type="submit">Показать</button>
        </div>
      </form>
    </div>
  </div>

  <div id="jwstGallery" class="row g-3">
    <div class="col-12 text-center text-muted py-5">Загрузка...</div>
  </div>

  <div id="jwstInfo" class="text-muted small mt-3"></div>

  <div class="text-center mt-4">
    <button id="loadMore" class="btn btn-outline-light" style="display:none">Загрузить ещё</button>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const gallery = document.getElementById('jwstGallery');
  const info = document.getElementById('jwstInfo');
  const form = document.getElementById('jwstFilter');
  const loadMoreBtn = document.getElementById('loadMore');
  let currentPage = 1;
  let currentParams = {};

  function toggleInputs() {
    const src = document.getElementById('srcSel').value;
    document.getElementById('suffixInp').closest('.col-sm-2').style.display = src === 'suffix' ? '' : 'none';
    document.getElementById('progInp').closest('.col-sm-2').style.display = src === 'program' ? '' : 'none';
  }

  document.getElementById('srcSel').addEventListener('change', toggleInputs);
  toggleInputs();

  async function loadFeed(params, append = false) {
    if (!append) {
      gallery.innerHTML = '<div class="col-12 text-center text-muted py-5">Загрузка...</div>';
      currentPage = 1;
    }
    params.page = currentPage;

    try {
      const url = '/api/jwst/feed?' + new URLSearchParams(params).toString();
      const r = await fetch(url);
      const js = await r.json();

      if (!append) gallery.innerHTML = '';

      (js.items || []).forEach((it, i) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        col.style.animationDelay = `${i * 0.05}s`;
        col.innerHTML = `
          <div class="card h-100 jwst-card">
            <a href="${it.link || it.url}" target="_blank" rel="noreferrer">
              <img src="${it.url}" class="card-img-top" alt="JWST" loading="lazy" style="height:200px;object-fit:cover">
            </a>
            <div class="card-body p-2">
              <p class="card-text small mb-1">${(it.caption || '').replace(/</g, '&lt;')}</p>
              <div class="d-flex flex-wrap gap-1">
                ${(it.inst || []).map(inst => `<span class="badge bg-secondary">${inst}</span>`).join('')}
              </div>
            </div>
          </div>
        `;
        gallery.appendChild(col);
      });

      info.textContent = `Источник: ${js.source} · Показано: ${gallery.children.length}`;
      loadMoreBtn.style.display = (js.items?.length >= parseInt(params.perPage || 24)) ? '' : 'none';
      currentParams = params;
    } catch(e) {
      gallery.innerHTML = '<div class="col-12 text-center text-danger py-5">Ошибка загрузки</div>';
    }
  }

  form.addEventListener('submit', ev => {
    ev.preventDefault();
    const fd = new FormData(form);
    loadFeed(Object.fromEntries(fd.entries()));
  });

  loadMoreBtn.addEventListener('click', () => {
    currentPage++;
    loadFeed(currentParams, true);
  });

  // Initial load
  loadFeed({ source: 'jpg', perPage: 24 });
});
</script>

<style>
  .jwst-card {
    transition: transform 0.2s, box-shadow 0.2s;
    animation: cardFadeIn 0.4s ease-out backwards;
  }
  .jwst-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(138,208,255,0.2);
  }
  @keyframes cardFadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>
@endsection

