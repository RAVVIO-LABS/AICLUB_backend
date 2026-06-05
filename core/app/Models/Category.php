<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use GlobalStatus;

    protected $appends =['image_path', 'image_url'];
    public function subCategories(){
        return $this->hasMany(SubCategory::class);   
    }

    public function courses(){
        return $this->hasMany(Course::class);    
    }

    public function getImagePathAttribute(){
        return getImage(getFilePath('category'));
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return getImage(null);
        }

        $path = trim(getFilePath('category'), '/') . '/' . ltrim((string) $this->image, '/');
        $uploadDisk = env('UPLOAD_DISK', 's3');
        $s3DriverAvailable = class_exists(\League\Flysystem\AwsS3V3\PortableVisibilityConverter::class);

        if ($uploadDisk === 's3' && $s3DriverAvailable) {
            try {
                return Storage::disk('s3')->temporaryUrl($path, now()->addHours(6));
            } catch (\Throwable $e) {
                return getImage($path);
            }
        }

        return getImage($path);
    }
}
