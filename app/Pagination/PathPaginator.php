<?php

namespace App\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class PathPaginator extends LengthAwarePaginator
{
    public function url($page): string
    {
        $page = $page <= 0 ? 1 : (int) $page;
        $base = rtrim($this->path(), '/');
        $url = $page <= 1 ? $base : $base.'/page/'.$page;
        $query = Arr::except($this->query, [$this->pageName]);

        if ($query !== []) {
            $url .= '?'.Arr::query($query);
        }

        return $url.$this->buildFragment();
    }
}
