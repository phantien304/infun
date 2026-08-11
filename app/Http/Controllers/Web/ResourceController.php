<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class ResourceController extends Controller
{
    public function zone(Request $request)
    {
        $rows = $this->zoneRepo->listAllCached()
            ->map(fn ($zone) => [
                'id'   => (int) $zone->id,
                'name' => (string) ($zone->description->name ?? ''),
            ])
            ->values();

        $response = respondSuccess($rows);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->setEtag(md5((string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    public function district(Request $request)
    {
        $zoneId = (int) $request->get('zone_id', 0);
        if ($zoneId <= 0) {
            return respondSuccess([]);
        }

        $rows = $this->districtRepo->listByZone($zoneId)
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

        $rows = $this->wardRepo->listByDistrict($districtId)
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
