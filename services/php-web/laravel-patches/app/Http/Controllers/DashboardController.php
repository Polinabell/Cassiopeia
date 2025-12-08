<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\JwstHelper;
use App\Services\RustApiService;
use App\Repositories\CmsRepository;

class DashboardController extends Controller
{
    public function index(RustApiService $rust, CmsRepository $cms)
    {
        $issResp = $rust->get('/last');
        $iss = ($issResp['ok'] ?? false) ? ($issResp['data'] ?? []) : [];

        $cmsBlock = $cms->findBlock('dashboard_experiment');
        $cmsHtml = $cmsBlock ? $this->sanitize($cmsBlock['content']) : '<div class="text-muted">блок не найден</div>';

        // фронт сам дернёт /api/iss/trend
        return view('dashboard', [
            'iss' => $iss,
            'trend' => [],
            'jw_gallery' => [], // не нужно сервером
            'jw_observation_raw' => [],
            'jw_observation_summary' => [],
            'jw_observation_images' => [],
            'jw_observation_files' => [],
            'metrics' => [
                'iss_speed' => $iss['payload']['velocity'] ?? null,
                'iss_alt'   => $iss['payload']['altitude'] ?? null,
                'neo_total' => 0,
            ],
            'cms_block' => $cmsHtml,
        ]);
    }

    /**
     * /api/jwst/feed — серверный прокси/нормализатор JWST картинок.
     * QS:
     *  - source: jpg|suffix|program (default jpg)
     *  - suffix: напр. _cal, _thumb, _crf
     *  - program: ID программы (число)
     *  - instrument: NIRCam|MIRI|NIRISS|NIRSpec|FGS
     *  - page, perPage
     */
    public function jwstFeed(Request $r)
    {
        $src   = $r->query('source', 'jpg');
        $sfx   = trim((string)$r->query('suffix', ''));
        $prog  = trim((string)$r->query('program', ''));
        $instF = strtoupper(trim((string)$r->query('instrument', '')));
        $page  = max(1, (int)$r->query('page', 1));
        $per   = max(1, min(60, (int)$r->query('perPage', 24)));

        $jw = new JwstHelper();

        $guessInstrument = function(array $it): array {
            $cand = [];
            foreach (($it['details']['instruments'] ?? []) as $I) {
                if (is_array($I) && !empty($I['instrument'])) $cand[] = strtoupper($I['instrument']);
            }
            foreach (['instrument','inst','camera','detector'] as $k) {
                if (!empty($it[$k]) && is_string($it[$k])) $cand[] = strtoupper($it[$k]);
            }
            $fields = [$it['details']['suffix'] ?? '', $it['suffix'] ?? '', $it['url'] ?? '', $it['location'] ?? '', $it['thumbnail'] ?? '', $it['id'] ?? '', $it['observation_id'] ?? ''];
            foreach ($fields as $f) {
                $s = strtolower((string)$f);
                if (str_contains($s, 'nircam') || str_contains($s,'_nrc')) $cand[] = 'NIRCam';
                if (str_contains($s, 'miri')) $cand[] = 'MIRI';
                if (str_contains($s, 'niriss')) $cand[] = 'NIRISS';
                if (str_contains($s, 'nirspec') || str_contains($s,'nrs')) $cand[] = 'NIRSpec';
                if (str_contains($s, 'fgs')) $cand[] = 'FGS';
            }
            return array_values(array_unique(array_filter($cand)));
        };

        // выбираем эндпоинт
        $path = 'all/type/jpg';
        if ($src === 'suffix' && $sfx !== '') $path = 'all/suffix/'.ltrim($sfx,'/');
        if ($src === 'program' && $prog !== '') $path = 'program/id/'.rawurlencode($prog);

        $resp = $jw->get($path, ['page'=>$page, 'perPage'=>$per]);
        $list = $resp['body'] ?? ($resp['data'] ?? (is_array($resp) ? $resp : []));

        $items = [];
        foreach ($list as $it) {
            if (!is_array($it)) continue;

            // выбираем валидную картинку
            $url = null;
            $loc = $it['location'] ?? $it['url'] ?? null;
            $thumb = $it['thumbnail'] ?? null;
            foreach ([$loc, $thumb] as $u) {
                if (is_string($u) && preg_match('~\.(jpg|jpeg|png)(\?.*)?$~i', $u)) { $url = $u; break; }
            }
            if (!$url) {
                $url = \App\Support\JwstHelper::pickImageUrl($it);
            }
            if (!$url) continue;

            // фильтр по инструменту
            $instList = $guessInstrument($it);
            if ($instF && $instList && !in_array($instF, $instList, true)) continue;
            if ($instF && !$instList) {
                // если не смогли угадать — пробуем по полю instrument (строка) или пропускаем
                $rawInst = strtoupper((string)($it['instrument'] ?? $it['details']['instrument'] ?? ''));
                if ($rawInst && $rawInst !== $instF) continue;
                if (!$rawInst) continue; // нет маркеров инструмента — не засоряем выдачу
                $instList = [$rawInst];
            }

            $items[] = [
                'url'      => $url,
                'obs'      => (string)($it['observation_id'] ?? $it['observationId'] ?? ''),
                'program'  => (string)($it['program'] ?? ''),
                'suffix'   => (string)($it['details']['suffix'] ?? $it['suffix'] ?? ''),
                'inst'     => $instList,
                'caption'  => trim(
                    (($it['observation_id'] ?? '') ?: ($it['id'] ?? '')) .
                    ' · P' . ($it['program'] ?? '-') .
                    (($it['details']['suffix'] ?? '') ? ' · ' . $it['details']['suffix'] : '') .
                    ($instList ? ' · ' . implode('/', $instList) : '')
                ),
                'link'     => $loc ?: $url,
            ];
            if (count($items) >= $per) break;
        }

        return response()->json([
            'source' => $path,
            'count'  => count($items),
            'items'  => $items,
        ]);
    }

    private function sanitize(string $html): string {
        return strip_tags($html, '<p><b><strong><i><em><ul><ol><li><br><h3><h4><code>');
    }
}
