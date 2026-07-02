<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entities\District;
use App\Models\Entities\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ResourceController extends Controller
{
    public function zone()
    {
        $rows = $this->zoneRepo->listAllCached()
            ->map(fn ($zone) => [
                'id'   => (int) $zone->id,
                'name' => (string) ($zone->description->name ?? ''),
            ])
            ->values();

        return respondSuccess($rows);
    }

    public function district(Request $request)
    {
        $zoneId = (int) $request->get('zone_id', 0);
        if ($zoneId <= 0) {
            return respondSuccess([]);
        }

        $rows = District::query()
            ->where('zone_id', $zoneId)
            ->with('description')
            ->orderBy('id')
            ->get()
            ->map(fn ($district) => [
                'id'   => (int) $district->id,
                'name' => (string) ($district->description->name ?? ''),
            ])
            ->values();

        return respondSuccess($rows);
    }

    public function ward(Request $request)
    {
        $districtId = (int) $request->get('district_id', 0);
        if ($districtId <= 0) {
            return respondSuccess([]);
        }

        $rows = Ward::query()
            ->where('district_id', $districtId)
            ->with('description')
            ->orderBy('id')
            ->get()
            ->map(fn ($ward) => [
                'id'   => (int) $ward->id,
                'name' => (string) ($ward->description->name ?? ''),
            ])
            ->values();

        return respondSuccess($rows);
    }

    public function zoneShipping(Request $request)
    {
        $zoneId = (int) $request->get('zone_id', 0);
        Cookie::queue(
            (string) setting('cookie.shipping_zone'),
            (string) $zoneId,
            (int) getCoreConfig('cookie.time'),
        );

        return respondMessage();
    }
}
