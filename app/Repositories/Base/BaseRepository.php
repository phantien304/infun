<?php

namespace App\Repositories\Base;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected $model;

    public function __construct(protected Application $app)
    {
        $this->makeModel();
    }

    abstract public function model(): string;

    public function makeModel()
    {
        $model = $this->app->make($this->model());
        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }
        return $this->model = $model;
    }

    public function resetModel()
    {
        return $this->makeModel();
    }

    public function transaction(\Closure $callback)
    {
        return DB::transaction($callback);
    }

    public function getDetail(int $id)
    {
    }

    public function paginate($limit = 15)
    {
        return $this->resetModel()->paginate($limit);
    }

    public function getParams()
    {
        return request()->except(['json']);
    }

    public function getDate($date, $format = 'Y-m-d')
    {
        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            logError($e->getMessage());
            return Carbon::now()->format($format);
        }
    }

    public function convertFormatDate($date, $fromFormat = 'd/m/Y', $toFormat = 'Y-m-d')
    {
        try {
            Carbon::parse($date);
            return $date;
        } catch (\Exception $e) {
            return Carbon::createFromFormat($fromFormat, $date)->format($toFormat);
        }
    }
}
