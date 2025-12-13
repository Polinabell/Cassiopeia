@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">📡 Телеметрия (Legacy)</h3>
    <div class="btn-group">
      <a href="/telemetry/download/csv?limit={{ $filter['limit'] }}&sort={{ $filter['sort'] }}&dir={{ $filter['dir'] }}" class="btn btn-outline-light btn-sm">
        ⬇ CSV
      </a>
      <a href="/telemetry/download/xlsx?limit={{ $filter['limit'] }}&sort={{ $filter['sort'] }}&dir={{ $filter['dir'] }}" class="btn btn-outline-light btn-sm">
        ⬇ XLSX
      </a>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="get">
        <div class="col-sm-3">
          <label class="form-label small text-muted">Поиск</label>
          <input type="text" name="q" value="{{ $filter['q'] }}" class="form-control form-control-sm" placeholder="файл / значение">
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Сортировка</label>
          <select name="sort" class="form-select form-select-sm">
            @foreach($columns as $col)
              <option value="{{ $col }}" {{ $filter['sort'] === $col ? 'selected' : '' }}>{{ $col }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Направление</label>
          <select name="dir" class="form-select form-select-sm">
            <option value="desc" {{ $filter['dir'] === 'desc' ? 'selected' : '' }}>По убыванию ↓</option>
            <option value="asc" {{ $filter['dir'] === 'asc' ? 'selected' : '' }}>По возрастанию ↑</option>
          </select>
        </div>
        <div class="col-sm-2">
          <label class="form-label small text-muted">Лимит</label>
          <input type="number" name="limit" min="1" max="500" value="{{ $filter['limit'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-1">
          <button class="btn btn-primary btn-sm w-100" type="submit">Применить</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="telemetryTable">
          <thead class="table-dark">
            <tr>
              @foreach($columns as $col)
                @php
                  $isCurrent = $filter['sort'] === $col;
                  $nextDir = ($isCurrent && $filter['dir'] === 'asc') ? 'desc' : 'asc';
                  $arrow = $isCurrent ? ($filter['dir'] === 'asc' ? '↑' : '↓') : '';
                @endphp
                <th>
                  <a href="?sort={{ $col }}&dir={{ $nextDir }}&limit={{ $filter['limit'] }}&q={{ urlencode($filter['q']) }}" class="text-white text-decoration-none">
                    {{ $col }} {!! $arrow !!}
                  </a>
                </th>
              @endforeach
              <th>valid</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $row)
              @php
                $valid = ($row['voltage'] >= 3.0 && $row['voltage'] <= 15.0 && $row['temp'] >= -60 && $row['temp'] <= 100);
              @endphp
              <tr class="{{ $valid ? '' : 'table-warning' }}">
                <td>{{ $row['id'] }}</td>
                <td><code>{{ $row['recorded_at'] }}</code></td>
                <td>{{ number_format($row['voltage'], 2, '.', ' ') }}</td>
                <td>{{ number_format($row['temp'], 2, '.', ' ') }}</td>
                <td class="text-break" style="max-width:200px">{{ $row['source_file'] }}</td>
                <td>
                  @if($valid)
                    <span class="badge bg-success">ИСТИНА</span>
                  @else
                    <span class="badge bg-danger">ЛОЖЬ</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="{{ count($columns)+1 }}" class="text-center text-muted py-4">Нет данных</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3 text-muted small">
    Показано {{ count($rows) }} записей. Формат CSV/XLSX включает: timestamp, числа, логические значения (ИСТИНА/ЛОЖЬ), строки.
  </div>
</div>

<style>
  .table-warning { background: rgba(255, 193, 7, 0.15) !important; }
  .table-dark th a:hover { color: var(--cosmo-accent) !important; }
  @keyframes fadeInRow { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  #telemetryTable tbody tr { animation: fadeInRow 0.3s ease-out backwards; }
  @for($i = 0; $i < 20; $i++)
    #telemetryTable tbody tr:nth-child({{ $i + 1 }}) { animation-delay: {{ $i * 0.03 }}s; }
  @endfor
</style>
@endsection

