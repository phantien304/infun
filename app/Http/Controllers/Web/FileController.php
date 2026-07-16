<?php

namespace App\Http\Controllers\Web;

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

        $file = $this->uploadedFile();
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
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png', 'mimetypes:image/jpeg,image/png', 'max:2048'],
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    protected function uploadedFile()
    {
        return request()->file('file');
    }

    protected function disk(): string
    {
        return (string) config('media.image_disk', 'image');
    }
}
