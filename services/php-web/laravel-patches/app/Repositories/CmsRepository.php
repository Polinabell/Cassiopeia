<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class CmsRepository
{
    public function findPage(string $slug): ?array
    {
        $row = DB::table('cms_pages')
            ->select(['title', 'body'])
            ->where('slug', $slug)
            ->first();
        return $row ? ['title' => $row->title, 'body' => $row->body] : null;
    }

    public function findBlock(string $slug): ?array
    {
        $row = DB::table('cms_blocks')
            ->select(['title', 'content'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
        return $row ? ['title' => $row->title, 'content' => $row->content] : null;
    }
}

