<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Support\JwstHelper;

class JwstController extends Controller
{
    public function index(Request $r)
    {
        return view('jwst', [
            'filter' => [
                'source' => $r->query('source', 'jpg'),
                'suffix' => $r->query('suffix', ''),
                'program' => $r->query('program', ''),
                'instrument' => $r->query('instrument', ''),
                'perPage' => $r->query('perPage', 24),
            ],
        ]);
    }

    /**
     * JWST feed endpoint with filtering.
     */
    public function feed(Request $r)
    {
        $src   = $r->query('source', 'jpg');
        $sfx   = trim((string)$r->query('suffix', ''));
        $prog  = trim((string)$r->query('program', ''));
        $instF = strtoupper(trim((string)$r->query('instrument', '')));
        $page  = max(1, (int)$r->query('page', 1));
        $per   = max(1, min(60, (int)$r->query('perPage', 24)));
        $search = strtolower(trim((string)$r->query('q', '')));

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

        $path = 'all/type/jpg';
        if ($src === 'suffix' && $sfx !== '') $path = 'all/suffix/'.ltrim($sfx,'/');
        if ($src === 'program' && $prog !== '') $path = 'program/id/'.rawurlencode($prog);

        $resp = $jw->get($path, ['page'=>$page, 'perPage'=>$per]);
        $list = $resp['body'] ?? ($resp['data'] ?? (is_array($resp) ? $resp : []));

        $items = [];
        foreach ($list as $it) {
            if (!is_array($it)) continue;

            $url = null;
            $loc = $it['location'] ?? $it['url'] ?? null;
            $thumb = $it['thumbnail'] ?? null;
            foreach ([$loc, $thumb] as $u) {
                if (is_string($u) && preg_match('~\.(jpg|jpeg|png)(\?.*)?$~i', $u)) { $url = $u; break; }
            }
            if (!$url) {
                $url = JwstHelper::pickImageUrl($it);
            }
            if (!$url) continue;

            $instList = $guessInstrument($it);
            if ($instF && $instList && !in_array($instF, $instList, true)) continue;
            if ($instF && !$instList) {
                $rawInst = strtoupper((string)($it['instrument'] ?? $it['details']['instrument'] ?? ''));
                if ($rawInst && $rawInst !== $instF) continue;
                if (!$rawInst) continue;
                $instList = [$rawInst];
            }

            $caption = trim(
                (($it['observation_id'] ?? '') ?: ($it['id'] ?? '')) .
                ' · P' . ($it['program'] ?? '-') .
                (($it['details']['suffix'] ?? '') ? ' · ' . $it['details']['suffix'] : '') .
                ($instList ? ' · ' . implode('/', $instList) : '')
            );

            // Search filter
            if ($search !== '' && stripos($caption, $search) === false) {
                continue;
            }

            $items[] = [
                'url'      => $url,
                'obs'      => (string)($it['observation_id'] ?? $it['observationId'] ?? ''),
                'program'  => (string)($it['program'] ?? ''),
                'suffix'   => (string)($it['details']['suffix'] ?? $it['suffix'] ?? ''),
                'inst'     => $instList,
                'caption'  => $caption,
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
}

