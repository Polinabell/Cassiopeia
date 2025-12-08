<?php
namespace App\Http\Controllers;
use App\Repositories\CmsRepository;
use Illuminate\Support\Str;

class CmsController extends Controller {
  public function page(string $slug, CmsRepository $repo) {
    $row = $repo->findBlock($slug);
    if (!$row) abort(404);
    $safe = $this->sanitize($row['content']);
    return response()->view('cms.page', ['title' => $row['title'], 'html' => $safe]);
  }

  private function sanitize(string $html): string {
    return strip_tags($html, '<p><b><strong><i><em><ul><ol><li><br><h3><h4><code>');
  }
}
