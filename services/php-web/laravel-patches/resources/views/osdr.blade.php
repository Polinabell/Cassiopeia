@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3 class="mb-3">📁 NASA OSDR — Open Science Data Repository</h3>
  <div class="text-muted small mb-3">Источник: {{ $src ?? 'N/A' }}</div>

  @if(!empty($error))
    <div class="alert alert-warning">{{ $error }}</div>
  @endif

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-sm-3">
          <label class="form-label small text-muted">Поиск (dataset/title)</label>
          <input type="text" name="q" value="{{ $filter['q'] ?? '' }}" class="form-control form-control-sm" placeholder="OSD- или текст">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Сортировка</label>
          <select name="sort" class="form-select form-select-sm">
            <option value="inserted_at" {{ ($filter['sort'] ?? '') === 'inserted_at' ? 'selected' : '' }}>Дата добавления</option>
            <option value="updated_at" {{ ($filter['sort'] ?? '') === 'updated_at' ? 'selected' : '' }}>Дата обновления</option>
            <option value="dataset_id" {{ ($filter['sort'] ?? '') === 'dataset_id' ? 'selected' : '' }}>Dataset ID</option>
            <option value="title" {{ ($filter['sort'] ?? '') === 'title' ? 'selected' : '' }}>Название</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Направление</label>
          <select name="dir" class="form-select form-select-sm">
            <option value="desc" {{ ($filter['dir'] ?? 'desc') === 'desc' ? 'selected' : '' }}>По убыванию ↓</option>
            <option value="asc" {{ ($filter['dir'] ?? 'desc') === 'asc' ? 'selected' : '' }}>По возрастанию ↑</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Лимит</label>
          <input type="number" min="1" max="300" name="limit" value="{{ $filter['limit'] ?? 100 }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-1">
          <button class="btn btn-sm btn-primary w-100" type="submit">Применить</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
  <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="osdrTable">
          <thead class="table-dark">
        <tr>
          <th>#</th>
              <th>
                <a href="?sort=dataset_id&dir={{ ($filter['sort'] ?? '') === 'dataset_id' && ($filter['dir'] ?? '') === 'asc' ? 'desc' : 'asc' }}&q={{ urlencode($filter['q'] ?? '') }}&limit={{ $filter['limit'] ?? 100 }}" class="text-white text-decoration-none">
                  dataset_id {{ ($filter['sort'] ?? '') === 'dataset_id' ? (($filter['dir'] ?? '') === 'asc' ? '↑' : '↓') : '' }}
                </a>
              </th>
              <th>
                <a href="?sort=title&dir={{ ($filter['sort'] ?? '') === 'title' && ($filter['dir'] ?? '') === 'asc' ? 'desc' : 'asc' }}&q={{ urlencode($filter['q'] ?? '') }}&limit={{ $filter['limit'] ?? 100 }}" class="text-white text-decoration-none">
                  title {{ ($filter['sort'] ?? '') === 'title' ? (($filter['dir'] ?? '') === 'asc' ? '↑' : '↓') : '' }}
                </a>
              </th>
          <th>REST_URL</th>
              <th>
                <a href="?sort=updated_at&dir={{ ($filter['sort'] ?? '') === 'updated_at' && ($filter['dir'] ?? '') === 'asc' ? 'desc' : 'asc' }}&q={{ urlencode($filter['q'] ?? '') }}&limit={{ $filter['limit'] ?? 100 }}" class="text-white text-decoration-none">
                  updated_at {{ ($filter['sort'] ?? '') === 'updated_at' ? (($filter['dir'] ?? '') === 'asc' ? '↑' : '↓') : '' }}
                </a>
              </th>
              <th>
                <a href="?sort=inserted_at&dir={{ ($filter['sort'] ?? '') === 'inserted_at' && ($filter['dir'] ?? '') === 'asc' ? 'desc' : 'asc' }}&q={{ urlencode($filter['q'] ?? '') }}&limit={{ $filter['limit'] ?? 100 }}" class="text-white text-decoration-none">
                  inserted_at {{ ($filter['sort'] ?? '') === 'inserted_at' ? (($filter['dir'] ?? '') === 'asc' ? '↑' : '↓') : '' }}
                </a>
              </th>
          <th>raw</th>
        </tr>
      </thead>
      <tbody>
          @forelse($items as $i => $row)
            <tr style="animation: rowFadeIn 0.3s ease-out backwards; animation-delay: {{ $i * 0.02 }}s">
          <td>{{ $row['id'] }}</td>
              <td><code>{{ $row['dataset_id'] ?? '—' }}</code></td>
              <td style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $row['title'] ?? '' }}">
            {{ $row['title'] ?? '—' }}
          </td>
          <td>
            @if(!empty($row['rest_url']))
                  <a href="{{ $row['rest_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-light">🔗</a>
            @else — @endif
          </td>
              <td><small>{{ $row['updated_at'] ?? '—' }}</small></td>
              <td><small>{{ $row['inserted_at'] ?? '—' }}</small></td>
          <td>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">JSON</button>
          </td>
        </tr>
        <tr class="collapse" id="raw-{{ $row['id'] }}-{{ md5($row['dataset_id'] ?? (string)$row['id']) }}">
              <td colspan="7" class="bg-dark">
                @php
                  $rawJson = json_encode($row['raw'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                  if (strlen($rawJson) > 4000) {
                    $rawJson = substr($rawJson, 0, 4000) . "\n… (truncated)";
                  }
                @endphp
                <pre class="mb-0 text-light small" style="max-height:260px;overflow:auto">{{ $rawJson }}</pre>
          </td>
        </tr>
      @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Нет данных</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
  </div>

  <div class="mt-3 text-muted small">
    Показано {{ count($items) }} записей
  </div>
</div>

<style>
  @keyframes rowFadeIn {
    from { opacity: 0; transform: translateX(-10px); }
    to { opacity: 1; transform: translateX(0); }
  }
  .table-dark th a:hover { color: var(--cosmo-accent) !important; }
</style>
@endsection
