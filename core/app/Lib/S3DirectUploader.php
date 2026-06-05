<?php

namespace App\Lib;

use Aws\S3\S3Client;
use Illuminate\Support\Str;

class S3DirectUploader
{
    public static function createPresignedPutUrl(string $relativePath, string $fileName, string $contentType, int $minutes = 60): array
    {
        $disk = config('filesystems.disks.s3');
        $bucket = (string) ($disk['bucket'] ?? '');
        $region = (string) ($disk['region'] ?? '');

        if ($bucket === '' || $region === '') {
            throw new \RuntimeException('S3 configuration is missing bucket or region.');
        }

        $key = trim($relativePath, '/') . '/' . ltrim($fileName, '/');

        $clientConfig = [
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => (string) ($disk['key'] ?? ''),
                'secret' => (string) ($disk['secret'] ?? ''),
            ],
        ];

        // Optional performance mode: enable only when bucket transfer acceleration is configured.
        if ((bool) env('S3_TRANSFER_ACCELERATION', false)) {
            $clientConfig['use_accelerate_endpoint'] = true;
        }

        if (!empty($disk['endpoint'])) {
            $clientConfig['endpoint'] = $disk['endpoint'];
            $clientConfig['use_path_style_endpoint'] = (bool) ($disk['use_path_style_endpoint'] ?? false);
        }

        $client = new S3Client($clientConfig);

        $commandArgs = [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $contentType ?: 'application/octet-stream',
        ];

        $command = $client->getCommand('PutObject', $commandArgs);

        $request = $client->createPresignedRequest($command, sprintf('+%d minutes', max(1, $minutes)));

        return [
            'upload_url' => (string) $request->getUri(),
            'key' => $key,
            'file_name' => $fileName,
        ];
    }

    public static function safeFileName(string $originalName): string
    {
        $name = trim($originalName);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string) $base);
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'file';
        }
        return $base . '_' . Str::lower(Str::random(8)) . ($ext ? '.' . $ext : '');
    }
}
