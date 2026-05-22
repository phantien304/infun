<?php

namespace App\Repositories\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;

interface BaseRepositoryInterface
{
    public function list(?Request $request = null, ?int $perPage = null): LengthAwarePaginator;
    public function listAll(?Request $request = null): Collection;
    public function getParams();
    public function getDate($date, $format = 'Y-m-d');
    public function convertFormatDate($date, $fromFormat = 'd/m/Y', $toFormat = 'Y-m-d');
}
