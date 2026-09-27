<?php

namespace App\Support;

use App\Pagination\PathPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogPager
{
    public static function paginate(Builder $query, Request $request, string $basePath, int $perPage = 24): PathPaginator|RedirectResponse
    {
        if ($request->query->has('page')) {
            $page = max(1, (int) $request->query('page'));

            return redirect()->to(self::url($basePath, $page, $request->except('page')), 301);
        }

        $routePage = $request->route('page');
        if ($routePage !== null && (int) $routePage <= 1) {
            return redirect()->to(self::url($basePath, 1, $request->query()), 301);
        }

        $page = max(1, (int) ($routePage ?? 1));
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        if ($page > 1 && $page > $result->lastPage()) {
            abort(404);
        }

        return new PathPaginator(
            $result->items(),
            $result->total(),
            $result->perPage(),
            $result->currentPage(),
            [
                'path' => $basePath,
                'pageName' => 'page',
                'query' => $request->except(['page']),
            ],
        );
    }

    public static function url(string $basePath, int $page, array $query = []): string
    {
        $url = $page <= 1 ? $basePath : rtrim($basePath, '/').'/page/'.$page;
        unset($query['page']);
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }

    public static function isPathPaginator(mixed $cars): bool
    {
        return $cars instanceof LengthAwarePaginator;
    }
}
