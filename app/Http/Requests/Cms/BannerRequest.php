<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'type'       => 'required|in:image,slider',
            'position'   => 'required|in:top,bottom',
            'sort_order' => 'nullable|numeric',
            'theme'      => ['nullable', 'string', Rule::in((array) config('theme.available', []))],
            'page'                  => 'required|array|min:1',
            'page.*'                => 'string|max:50',
            'banner_descriptions'                 => 'array',
            'banner_descriptions.*.language_code' => 'required|string|max:11',

            'banner_values'                              => 'array',
            'banner_values.*.id'                          => 'nullable|integer',
            'banner_values.*.link'                        => 'nullable|string|max:255',
            'banner_values.*.sort_order'                   => 'nullable|numeric',
            'banner_values.*.media_type'                   => 'nullable|in:image,video',
            'banner_values.*.image'                        => 'nullable|string|max:255',
            'banner_values.*.video_provider'                => 'nullable|in:youtube,r2',
            'banner_values.*.video_url'                     => 'nullable|string|max:500',
            'banner_values.*.banner_value_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('banner_descriptions', []) as $i => $item) {
            $rules["banner_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        foreach ((array) $this->input('banner_values', []) as $i => $value) {
            $mediaType = $value['media_type'] ?? 'image';
            $rules["banner_values.$i.image"] = $mediaType === 'image'
                ? 'required|string|max:255'
                : 'nullable|string|max:255';
            $rules["banner_values.$i.video_provider"] = $mediaType === 'video'
                ? 'required|in:youtube,r2'
                : 'nullable|in:youtube,r2';
            $rules["banner_values.$i.video_url"] = $mediaType === 'video'
                ? 'required|string|max:500'
                : 'nullable|string|max:500';

            foreach ((array) ($value['banner_value_descriptions'] ?? []) as $j => $desc) {
                $rules["banner_values.$i.banner_value_descriptions.$j.title"] =
                    (($desc['language_code'] ?? '') === $defaultLang)
                        ? 'required|string|max:255'
                        : 'nullable|string|max:255';
                $rules["banner_values.$i.banner_value_descriptions.$j.content"] = 'nullable|string';
            }
        }

        return $rules;
    }
}
