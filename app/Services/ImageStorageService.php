<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorageService
{
    public function uploadPublicImage(UploadedFile $file, string $directory, array $context = []): string
    {
        $directory = trim($directory, '/');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $path = $directory . '/' . Str::uuid() . '.' . $extension;

        try {
            $contents = file_get_contents($file->getRealPath());

            if ($contents === false) {
                throw new RuntimeException('Unable to read uploaded image file.');
            }

            $uploaded = Storage::disk('supabase')->put($path, $contents, [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType() ?: 'image/jpeg',
            ]);

            if (!$uploaded) {
                throw new RuntimeException('Supabase storage put returned false.');
            }

            return $path;
        } catch (\Throwable $exception) {
            Log::error('Image upload failed', array_merge($context, [
                'disk' => 'supabase',
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'exception' => $exception->getMessage(),
            ]));

            throw new RuntimeException('Image upload failed.', 0, $exception);
        }
    }

    public function publicUrl(string $path): string
    {
        return Storage::disk('supabase')->url($path);
    }
}
