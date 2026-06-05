<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CourseResource extends Model
{
    protected $appends = ['file_url'];

    public function course(){
        return $this->belongsTo(Course::class);
    }

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file) {
            return null;
        }

        $relativePath = trim(getFilePath('resources'), '/') . '/' . ltrim($this->file, '/');
        $localPath = public_path($relativePath);

        if (is_file($localPath)) {
            return asset($relativePath);
        }

        $disk = env('UPLOAD_DISK', 's3');
        if ($disk === 's3') {
            try {
                $mime = null;
                try {
                    $mime = Storage::disk($disk)->mimeType($relativePath);
                } catch (\Throwable $e) {
                    $mime = null;
                }
                if (!$mime) {
                    $ext = strtolower((string) pathinfo($this->file, PATHINFO_EXTENSION));
                    $mimeMap = [
                        'pdf' => 'application/pdf',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        'mp4' => 'video/mp4',
                        'webm' => 'video/webm',
                        'mov' => 'video/quicktime',
                    ];
                    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
                }

                try {
                    return Storage::disk($disk)->temporaryUrl($relativePath, now()->addMinutes(30), [
                        'ResponseContentType' => $mime,
                        'ResponseContentDisposition' => 'inline',
                    ]);
                } catch (\Throwable $e) {
                    return Storage::disk($disk)->url($relativePath);
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}
