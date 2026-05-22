<?php

namespace App\Helpers;

use Illuminate\Support\Arr;

trait RouteAs
{
    protected function buildAs($groups, &$parameters, $type = 'param')
    {
        $area = data_get($groups, '0.area');
        if (!$area) return;

        if ($type == 'param') {
            if (Arr::has($parameters, '1.as')) {
                $as = $area . '.' . $parameters[1]['as'];
                return Arr::set($parameters, '1.as', $as);
            }

            if (Arr::has($parameters, '2.as')) {
                $as = $area . '.' . $parameters[2]['as'];
                return  Arr::set($parameters, '2.as', $as);
            }
            return true;
        }

        if (Arr::has($parameters, 'as')) {
            $as = $area . '.' . $parameters['as'];
            return  Arr::set($parameters, 'as', $as);
        }
    }
}
