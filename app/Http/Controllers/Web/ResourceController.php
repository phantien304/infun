<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Entities\District;
use App\Models\Entities\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * AJAX resource endpoint cho dropdown địa chỉ trong form checkout / account.
 * Trả JSON shape `{success, data:[{id, name}, ...]}` — frontend
 * `style.js::callResource(District|Ward)` đọc trực tiếp.
 *
 * Zone: dùng `zoneRepo->listAllCached()` (đã filter country_id default + cache
 * system). District / Ward: query model trực tiếp — chỉ "find by FK + load
 * description theo locale", không đủ phức tạp để cần repo riêng.
 *
 * Country mặc định đọc từ `getCoreConfig('zones.country_id_default')` (config
 * `core/config.php` mục `zones.country_id_default = 230` cho Việt Nam).
 */
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

        return successData('SearchSuccess', $rows, $rows->count());
    }

    public function district(Request $request)
    {
        $zoneId = (int) $request->get('zone_id', 0);
        if ($zoneId <= 0) {
            return successData('SearchSuccess', [], 0);
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

        return successData('SearchSuccess', $rows, $rows->count());
    }

    public function ward(Request $request)
    {
        $districtId = (int) $request->get('district_id', 0);
        if ($districtId <= 0) {
            return successData('SearchSuccess', [], 0);
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

        return successData('SearchSuccess', $rows, $rows->count());
    }

    public function zoneShipping(Request $request)
    {
        $zoneId = (int) $request->get('zone_id', 0);
        Cookie::queue(
            (string) setting('cookie.shipping_zone'),
            (string) $zoneId,
            (int) getCoreConfig('cookie.time'),
        );

        return successNoData('Success');
    }
}
