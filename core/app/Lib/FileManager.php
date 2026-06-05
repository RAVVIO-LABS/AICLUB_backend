<?php

namespace App\Lib;

use App\Constants\FileInfo;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileManager
{
    /*
    |--------------------------------------------------------------------------
    | File Manager
    |--------------------------------------------------------------------------
    |
    | FileManager class is using to manage edit, update, remove files. Developer
    | can manage any kind of files from here. But some limitations is here for image.
    | This class using a trait to manage the file paths and sizes. Developer can also
    | use this class as a helper function.
    |
    */

    /**
    * The file which will be uploaded
    *
    *
    * @var object
    */
	protected $file;

    /**
    * The path where will be uploaded
    *
    * @var string
    */
	public $path;

    /**
    * The size, if the file is image
    *
    * @var string
    */
	public $size;

    /**
    * Check the file is image or not
    *
    * @var boolean
    */
	protected $isImage;

    /**
    * Thumbnail version size, if required
    * and if the file is image
    *
    * @var string
    */
	public $thumb;

    /**
    * Old filename, which will be removed
    *
    * @var string
    */
	public $old;

    /**
    * Current filename, which is uploading
    *
    * @var string
    */
	public $filename;
    protected $disk;


    /**
    * Set the file and file type to properties if exist
    *
    * @param $file
    * @return void
    */
	public function __construct($file = null){
		$this->file = $file;
        $this->disk = env('UPLOAD_DISK', 's3');
		if ($file) {
			$imageExtensions = ['jpg','jpeg','png','JPG','JPEG','PNG'];
			if (in_array($file->getClientOriginalExtension(), $imageExtensions)) {
				$this->isImage = true;
			}else{
				$this->isImage = false;
			}
		}
	}

    /**
    * File upload process
    *
    * @return void
    */
	public function upload(){

        //create the directory if doesn't exists
		$path = $this->makeDirectory();
		if (!$path) throw new \Exception('File could not been created.');

        //remove the old file if exist
		if ($this->old) {
            $this->removeFile();
	    }

        //get the filename
        if(!$this->filename){
            $this->filename = $this->getFileName();
        }

        //upload file or image
	    if ($this->isImage == true) {
	    	$this->uploadImage();
	    }else{
	    	$this->uploadFile();
	    }
	}

    /**
    * Upload the file if this is image
    *
    * @return void
    */
	protected function uploadImage(){
        $manager = new ImageManager(new Driver());
        $image = $manager->read($this->file);

        //resize the
	    if ($this->size) {
	        $size = explode('x', strtolower($this->size));
	        $image->resize($size[0], $size[1]);
	    }
        $targetPath = trim($this->path, '/') . '/' . $this->filename;

        if ($this->useCloudDisk()) {
            $uploaded = Storage::disk($this->disk)->put($targetPath, (string) $image->encode(), [
                'ContentType' => $this->file->getMimeType() ?: 'application/octet-stream',
            ]);
            if ($uploaded === false) {
                Log::error('S3 image upload returned false', [
                    'disk' => $this->disk,
                    'target_path' => $targetPath,
                    'mime' => $this->file?->getMimeType(),
                    'bucket' => env('AWS_BUCKET'),
                    'region' => env('AWS_DEFAULT_REGION'),
                ]);
                throw new \Exception('Image upload to cloud storage failed.');
            }
        } else {
            //save the image
            $image->save($targetPath);
        }

        //save the image as thumbnail version
	    if ($this->thumb) {
            if ($this->old) {
                $this->removeFile(trim($this->path, '/') . '/thumb_' . $this->old);
            }
	        $thumb = explode('x', $this->thumb);
            $thumbPath = trim($this->path, '/') . '/thumb_' . $this->filename;
            $thumbImage = $manager->read($this->file)->resize($thumb[0], $thumb[1]);
            if ($this->useCloudDisk()) {
                $thumbUploaded = Storage::disk($this->disk)->put($thumbPath, (string) $thumbImage->encode(), [
                    'ContentType' => $this->file->getMimeType() ?: 'application/octet-stream',
                ]);
                if ($thumbUploaded === false) {
                    Log::error('S3 thumb upload returned false', [
                        'disk' => $this->disk,
                        'target_path' => $thumbPath,
                        'mime' => $this->file?->getMimeType(),
                        'bucket' => env('AWS_BUCKET'),
                        'region' => env('AWS_DEFAULT_REGION'),
                    ]);
                    throw new \Exception('Thumbnail upload to cloud storage failed.');
                }
            } else {
                $thumbImage->save($thumbPath);
            }
	    }
	}


    /**
    * Upload the file if this is not a image
    *
    * @return void
    */
	protected function uploadFile(){
        $targetPath = trim($this->path, '/') . '/' . $this->filename;
        if ($this->useCloudDisk()) {
            $uploaded = Storage::disk($this->disk)->putFileAs(trim($this->path, '/'), $this->file, $this->filename);
            if ($uploaded === false) {
                Log::error('S3 file upload returned false', [
                    'disk' => $this->disk,
                    'target_path' => $targetPath,
                    'mime' => $this->file?->getMimeType(),
                    'size_bytes' => $this->file?->getSize(),
                    'bucket' => env('AWS_BUCKET'),
                    'region' => env('AWS_DEFAULT_REGION'),
                ]);
                throw new \Exception('File upload to cloud storage failed.');
            }
        } else {
            $this->file->move($this->path, $this->filename);
        }
	}

    /**
    * Make directory doesn't exists
    * Developer can also call this method statically
    *
    * @param $location
    * @return string
    */
	public function makeDirectory($location = null){
		if (!$location) $location = $this->path;
        if ($this->useCloudDisk()) return true;
		if (file_exists($location)) return true;
    	return mkdir($location, 0755, true);
	}

    /**
    * Remove all directory inside the location
    * Developer can also call this method statically
    *
    * @param $location
    * @return void
    */
	public function removeDirectory($location = null){
		if (!$location) $location = $this->path;
		if (! is_dir($location)) {
	        throw new \InvalidArgumentException("$location must be a directory");
	    }
	    if (substr($location, strlen($location) - 1, 1) != '/') {
	        $location .= '/';
	    }
	    $files = glob($location . '*', GLOB_MARK);
	    foreach ($files as $file) {
	        if (is_dir($file)) {
	            static::removeDirectory($file);
	        } else {
	            unlink($file);
	        }
	    }
	    rmdir($location);
	}

    /**
    * Remove the file if exists
    * Developer can also call this method statically
    *
    * @param $path
    * @return void
    */
	public function removeFile($path = null)
	{
		if (!$path) $path = $this->path . '/' . $this->old;

        if ($this->useCloudDisk()) {
            Storage::disk($this->disk)->delete(ltrim($path, '/'));
        } else {
            file_exists($path) && is_file($path) ? @unlink($path) : false;
        }

	    if ($this->thumb) {
            $thumbPath = $this->path . '/thumb_' . $this->old;
            if ($this->useCloudDisk()) {
                Storage::disk($this->disk)->delete(ltrim($thumbPath, '/'));
            } else {
                file_exists($thumbPath) && is_file($thumbPath) ? @unlink($thumbPath) : false;
            }
	    }
	}

    protected function useCloudDisk(): bool
    {
        return $this->disk === 's3'
            && class_exists(\League\Flysystem\AwsS3V3\PortableVisibilityConverter::class);
    }

    /**
    * Generating the filename which is uploading
    *
    * @return string
    */
	protected function getFileName(){
		return uniqid() . time() . '.' . $this->file->getClientOriginalExtension();
	}

    /**
    * Get access of array from fileInfo method as non-static method.
    * Also get some others method
    *
    * @return string|void
    */
	public function __call($method,$args){
        $fileInfo = new FileInfo;
		$filePaths = $fileInfo->fileInfo();
		if (array_key_exists($method, $filePaths)) {
			$path = json_decode(json_encode($filePaths[$method]));
			return $path;
		}else{
			if (method_exists($this,$method)) {
				$this->$method(...$args);
			}else{
				throw new \Exception('File key or method doesn\'t exists.');
			}
		}
	}

    /**
    * Get access some non-static method as static method
    *
    * @return void
    */
	public static function __callStatic($method,$args){
		$selfClass = new FileManager;
		$selfClass->$method(...$args);
	}

}
