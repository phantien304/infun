<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;

/**
 * Class FileService
 * @package App\Services
 */
class MyStorage
{
    private $storageType;
    private $currentArea;

    public function __call($name, $arguments)
    {
        return call_user_func_array([Storage::class, $name], $arguments);
    }

    public function path($file)
    {
        return str_replace('/', '\\', storage_path($file));
    }

    /**
     * @param $fileName
     * @param $content
     * @return mixed
     */
    public function uploadToTmp($fileName, $content, $keepOriginalPath = false)
    {
        $newFilePath = $keepOriginalPath
            ? getTmpUploadDir() . $fileName
            : getTmpUploadDir(date('Y-m-d')) . '/' . $fileName;
        $this->deleteTmpDaily();
        if ($this->isUploadFile($content)) {
            $r = Storage::putFileAs(getTmpUploadDir(date('Y-m-d')), $content, $fileName);
            if (!$r) {
                throw new UploadException(trans('messages.file_upload_failed', ['file' => $newFilePath]));
            }
            return $newFilePath;
        }

        $r = $this->put($newFilePath, $content);
        if (!$r) {
            throw new UploadException(trans('messages.file_upload_failed', ['file' => $newFilePath]));
        }
        return $newFilePath;
    }

    /**
     * @param $fileName
     */
    public function download($fileName)
    {
    }

    public function url($fileName)
    {
        if (!$fileName) {
            return '';
        }
        if (str_contains($fileName, 'http')) {
            return $fileName;
        }
        $fileName = str_replace('\\', '/', $fileName);
        $diskName = $this->getStorageType();
        if ($diskName === 'logs') {
            return $fileName;
        }
        $url = Storage::disk($diskName)->url($fileName);
        return urldecode($url);
    }

    public function resizeImage(?string $path, $width = 50, $height = 50, $module = 'web')
    {
        if (!$path) {
            return '';
        }
        if (str_contains($path, 'http')) {
            return $path;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        $diskName = $this->getStorageType();
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
                return $this->url($path);
            }
            $cachePath = trim((string) (setting('folder_cache') ?: 'cache'), '/')
                . '/' . $width . 'x' . $height . '/' . $path;

            return $this->url($cachePath);
        }

        $fromPublic = false;

        if (!$disk->exists($path)) {
            if (is_file(public_path($path))) {
                $fromPublic = true;
            } else {
                $noImg = getModuleConfig('no_img', $module);
                if (!$noImg || !is_file(public_path($noImg))) {
                    return $noImg ? asset($noImg) : '';
                }
                $path = $noImg;
                $fromPublic = true;
            }
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') {
            return $fromPublic ? asset($path) : $this->url($path);
        }

        $cachePath = trim(setting('folder_cache'), '/')
            . '/' . $width . 'x' . $height
            . '/' . $path;

        if ($disk->exists($cachePath)) {
            return $this->url($cachePath);
        }

        if (!$disk->exists(dirname($cachePath))) {
            $disk->makeDirectory(dirname($cachePath));
        }

        try {
            $source = $fromPublic ? file_get_contents(public_path($path)) : $disk->get($path);
            $image = ImageManager::gd()->read($source);
            $image->coverDown($width, $height);
            $disk->put($cachePath, (string) $image->encodeByPath($cachePath));
        } catch (\Throwable $e) {
            logError($e->getMessage());
            return $fromPublic ? asset($path) : $this->url($path);
        }

        return $this->url($cachePath);
    }

    public function withOutUrl($fileName)
    {
        if (empty($fileName)) {
            return '';
        }

        $prefix = '__prefix__';
        $baseUrl = $this->url($prefix);
        $baseUrl = str_replace('__prefix__', '', $baseUrl);

        $fileName = urldecode($fileName);
        $baseUrl = urldecode($baseUrl);

        $cleanPath = str_replace($baseUrl, '', $fileName);
        return ltrim($cleanPath, '/');
    }

    public function moveFromTmpToMedia($filePath, $newName = '')
    {
        if (!Storage::exists($filePath)) {
            throw new UploadException(trans('messages.file_dose_not_exist', ['file' => $filePath]));
        }
        $newFilePath = getMediaDir($newName ? $newName : $filePath);
        $nameBackup = $newFilePath . '_' . time();
        if (Storage::exists($newFilePath)) {
            // rename
            Storage::move($newFilePath, $nameBackup);
        }
        try {
            $r = Storage::move($filePath, $newFilePath);
            if (!$r) {
                throw new UploadException(trans('messages.file_upload_failed', ['file' => $filePath]));
            }
            if (Storage::exists($nameBackup)) {
                // rename
                Storage::delete($nameBackup);
            }
            return $newFilePath;
        } catch (\Exception $exception) {
            // rollback
            if (Storage::exists($nameBackup)) {
                // rename
                Storage::move($nameBackup, $newFilePath);
            }
            throw $exception;
        }
    }

    public function put($file, $content)
    {
        if (!$this->isUploadFile($content)) {
            $content = $this->base64ToFile($content);
        }
        return Storage::put($file, $content);
    }

    public function base64ToFile($fileData)
    {
        @list($type, $fileData) = explode(';', $fileData);
        @list(, $fileData) = explode(',', $fileData);
        return base64_decode($fileData);
    }

    public function isUploadFile($data)
    {
        return $data instanceof UploadedFile;
    }

    /**
     * @return mixed
     */
    public function deleteTmpDaily()
    {
        $oldDir = getTmpUploadDir(today()->subDays(30)->format('Y-m-d'));
        if (Storage::exists($oldDir)) {
            Storage::deleteDirectory($oldDir);
        }
    }

    public function setStorageType($type)
    {
        $this->storageType = $type;
    }

    public function getStorageType()
    {
        return $this->storageType;
    }

    public function getStorage($type)
    {
        $this->setStorageType($type);
        return $this;
    }

    public function getCurrentArea()
    {
        return $this->currentArea ?: getCurrentArea();
    }

    public function setCurrentArea($area = '')
    {
        $this->currentArea = $area;
        return $this;
    }

    public function getAreaFilePath($fileName)
    {
        $area = getCoreConfig('area_mapping.' . $this->getCurrentArea());
        $fileName = str_replace('\\', '/', ltrim($fileName, '/'));

        if (str_starts_with($fileName, $area . '/')) {
            return $fileName;
        }
        return $area . '/' . $fileName;
    }

    public function putWithArea($fileName, $content)
    {
        return Storage::disk($this->getStorageType())->put($this->getAreaFilePath($fileName), $content);
    }

    public function putWithOutArea($folderName, $content, $hasOriginalName = false)
    {
        $storage = Storage::disk($this->getStorageType());
        if ($hasOriginalName) {
            return $content->storeAs($folderName, $content->getClientOriginalName());
        }
        return $storage->put($folderName, $content);
    }

    public function appendWithArea($fileName, $content)
    {
        return Storage::disk($this->getStorageType())->append($this->getAreaFilePath($fileName), $content);
    }

    public function prependWithArea($fileName, $content)
    {
        return Storage::disk($this->getStorageType())->prepend($this->getAreaFilePath($fileName), $content);
    }

    public function getWithArea($fileName)
    {
        return Storage::disk($this->getStorageType())->get($this->getAreaFilePath($fileName));
    }

    public function allFilesWithArea($directory = '')
    {
        return Storage::disk($this->getStorageType())->allFiles($this->getAreaFilePath($directory));
    }

    public function getPathPrefixWithArea()
    {
        $disk = Storage::disk($this->getStorageType());
        $config = config("filesystems.disks.{$this->getStorageType()}");
        $driver = $config['driver'] ?? 'local';
        if ($driver === 'local') {
            return $disk->getAdapter()->getPathPrefix();
        }
        return '';
    }

    public function pathWithArea($file)
    {
        return Storage::disk($this->getStorageType())->path($this->getAreaFilePath($file));
    }

    public function existsWithArea($fileName)
    {
        return Storage::disk($this->getStorageType())->exists($this->getAreaFilePath($fileName));
    }

    public function existsWithOutArea($fileName)
    {
        return Storage::disk($this->getStorageType())->exists($fileName);
    }

    public function makeDirectoryWithArea($fileName, $permissions = 0775, $option = true)
    {
        return Storage::disk($this->getStorageType())->makeDirectory($this->getAreaFilePath($fileName), $permissions, $option);
    }

    public function deleteWithArea($file)
    {
        return Storage::disk($this->getStorageType())->delete($this->getAreaFilePath($file));
    }

    public function deleteWithOutArea($file)
    {
        return Storage::disk($this->getStorageType())->delete($file);
    }

    public function deleteFilesWithArea($files = [])
    {
        try {
            foreach ($files as $file) {
                Storage::disk($this->getStorageType())->delete($this->getAreaFilePath($file));
            }
            return true;
        } catch (\Exception $exception) {
            return $exception;
        }
    }

    public function copyWithArea($from, $to)
    {
        return Storage::disk($this->getStorageType())->copy($this->getAreaFilePath($from), $this->getAreaFilePath($to));
    }

    public function copyWithOutArea($from, $to)
    {
        return Storage::disk($this->getStorageType())->copy($from, $to);
    }

    public function moveWithArea($from, $to)
    {
        return Storage::disk($this->getStorageType())->move($this->getAreaFilePath($from), $this->getAreaFilePath($to));
    }

    public function downloadWithArea($fileName, $name = null, array $headers = [])
    {
        return Storage::disk($this->getStorageType())->download($this->getAreaFilePath($fileName), $name, $headers);
    }

    public function sizeWithArea($fileName)
    {
        return Storage::disk($this->getStorageType())->size($this->getAreaFilePath($fileName));
    }

    public function lastModifiedWithArea($fileName)
    {
        return Storage::disk($this->getStorageType())->lastModified($this->getAreaFilePath($fileName));
    }

    public function filesWithArea($directory = null, $recursive = false)
    {
        return Storage::disk($this->getStorageType())->files($this->getAreaFilePath($directory), $recursive);
    }

    public function directoriesWithArea($directory = null, $recursive = false)
    {
        return Storage::disk($this->getStorageType())->directories($this->getAreaFilePath($directory), $recursive);
    }

    public function allDirectoriesWithArea($directory = null)
    {
        return Storage::disk($this->getStorageType())->allDirectories($this->getAreaFilePath($directory));
    }
}
