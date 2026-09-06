<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class GeoZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                          => 'required|string|max:32',
            'description'                   => 'nullable|string|max:255',
            'zone_to_geo_zones'             => 'nullable|array',
            'zone_to_geo_zones.*.country_id' => 'nullable|integer',
            'zone_to_geo_zones.*.zone_id'    => 'nullable|integer',
        ];
    }
}
