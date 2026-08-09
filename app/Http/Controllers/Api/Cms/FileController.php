<?php

namespace App\Http\Controllers\Api\Cms;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    public function upload(): JsonResponse
    {
        if ($error = $this->validationError()) {
            return respondUnprocessable($error, ['file' => [$error]]);
        }

        $file = request()->file('file');
        $path = $file->storeAs('infun/' . date('Y-m-d'), $file->getClientOriginalName(), $this->disk());

        if ($path) {
            ProcessImageUpload::dispatch($path, $this->disk(), (string) config('media.image_disk', 'image'));

            return respondCreated(
                ['path' => $path, 'name' => $file->getClientOriginalName()],
                trans('messages.UploadSuccess'),
            );
        }

        return respondError(trans('messages.UploadFailed'), 500);
    }

    protected function validationError(): ?string
    {
        $validator = Validator::make(request()->all(), [
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    public function uploadVideo(): JsonResponse
    {
        if ($error = $this->validationVideoError()) {
            return respondUnprocessable($error, ['file' => [$error]]);
        }

        $file = request()->file('file');
        $path = $file->storeAs('infun/video/' . date('Y-m-d'), $file->getClientOriginalName(), $this->disk());

        if ($path) {
            return respondCreated(
                ['path' => $path, 'name' => $file->getClientOriginalName()],
                trans('messages.UploadSuccess'),
            );
        }

        return respondError(trans('messages.UploadFailed'), 500);
    }

    protected function validationVideoError(): ?string
    {
        $validator = Validator::make(request()->all(), [
            'file' => [
                'required', 'file', 'mimes:mp4,mov,webm,avi',
                'mimetypes:video/mp4,video/webm,video/quicktime,video/x-msvideo',
                'max:51200',
            ],
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    protected function disk(): string
    {
        return (string) config('media.image_disk', 'image');
    }
}
