<?php

namespace App\Http\Controllers\Client\InfunStudio;

use Illuminate\Support\Facades\Validator;

class FileController extends BaseInfunStudioController
{
    public function upload()
    {
        $validator = $this->_validatorFile();
        if ($validator) {
            return errValidator($validator, 200);
        }
        $file = $this->_getStorage()->putWithOutModule('infun/' . date('Y-m-d'), $this->_getFile(), true);
        if ($file) {
            return successData('UploadSuccess', ['path' => $file, 'name' => $this->_getFile()->getClientOriginalName()]);
        }
        return errValidator('UploadFailed', 200);
    }

    protected function _validatorFile()
    {
        $validator = Validator::make(request()->all(), [
            'file' => [
                'required',
                'image',
                'file_extension:jpeg,jpg,png',
                'mimes:jpeg,jpg,png',
                'mimetypes:image/jpeg,image/png',
                'max:2048'
            ]
        ], $this->_getMessage());
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return null;
    }

    protected function _getFile()
    {
        return request()->file('file');
    }

    protected function _getStorage()
    {
        return storageImage()->getStorage('public');
    }

    protected function _getMessage()
    {
        return [
            'image' => 'Vui lòng upload ảnh.',
            'file_extension' => 'Tệp không đúng định dạng. Vui lòng chọn loại tệp: jpeg, jpg, png.',
            'mimes' => 'Tệp không đúng định dạng. Vui lòng chọn loại tệp: jpeg, jpg, png.',
            'mimetypes' => 'Tệp không đúng định dạng. Vui lòng chọn loại tệp: jpeg, jpg, png.',
            'max' => 'Tệp không lớn hơn 2MB. Vui lòng chọn lại.',
        ];
    }
}
