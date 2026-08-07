<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate cho store + update Menu (REST). Menu KHÔNG có bảng dịch (chỉ
 * title/position/theme phẳng — khác Category/Blog).
 */
class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission). Cho qua ở tầng request.
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => 'required|string|max:255',
            'position' => ['required', Rule::in(['top', 'footer'])],
            // Allowlist theo config/theme.php (giống ThemeManager::sanitize) —
            // KHÔNG FK vì theme là allowlist cấu hình, không phải bảng DB.
            'theme'    => ['nullable', 'string', Rule::in((array) config('theme.available', []))],
        ];
    }
}
