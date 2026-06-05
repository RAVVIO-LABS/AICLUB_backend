<?php

namespace App\Models;

use App\Models\CourseResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CourseLecture extends Model
{
    protected $appends =['video_path', 'video_url'];

    protected $casts = [
        'is_live_class' => 'boolean',
    ];

    public function course(){
        return $this->belongsTo(Course::class);
    }

    public function curriculum(){
        return $this->belongsTo(Curriculum::class);
    }

    public function resources(){
        return $this->hasMany(CourseResource::class, 'course_lecture_id');
    }

    public function liveSessions()
    {
        return $this->hasMany(CourseLiveSession::class, 'course_lecture_id');
    }



    public function getArticleAttribute($value)
    {
        $article = (string) ($value ?? '');
        if ($article === '' || !str_contains($article, '#rid=')) {
            return $article;
        }

        preg_match_all('/#rid=(\d+)/', $article, $matches);
        $ids = collect($matches[1] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $article;
        }

        $resourceMap = CourseResource::whereIn('id', $ids->all())
            ->where('course_lecture_id', $this->id)
            ->get()
            ->keyBy('id');

        return preg_replace_callback('/(<img[^>]*\ssrc=["\'])([^"\']*?)(#rid=(\d+))(\"|\')/i', function ($m) use ($resourceMap) {
            $resourceId = (int) ($m[4] ?? 0);
            $resource = $resourceMap->get($resourceId);
            if (!$resource || empty($resource->file_url)) {
                return $m[0];
            }

            return $m[1] . $resource->file_url . $m[3] . $m[5];
        }, $article);
    }

    public function getVideoPathAttribute(){
        $relativePath = trim(getFilePath('video'), '/');
        $disk = env('UPLOAD_DISK', 's3');

        if ($disk === 's3') {
            try {
                return rtrim(Storage::disk($disk)->url($relativePath), '/');
            } catch (\Throwable $e) {
                return asset($relativePath);
            }
        }

        return asset($relativePath);
    }

    public function getVideoUrlAttribute(): ?string
    {
        if (!$this->file) {
            return null;
        }

        $relativePath = trim(getFilePath('video'), '/') . '/' . ltrim($this->file, '/');
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
                        'mp4' => 'video/mp4',
                        'webm' => 'video/webm',
                        'mov' => 'video/quicktime',
                        'mkv' => 'video/x-matroska',
                        'avi' => 'video/x-msvideo',
                        'mpeg' => 'video/mpeg',
                        'vob' => 'video/dvd',
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
