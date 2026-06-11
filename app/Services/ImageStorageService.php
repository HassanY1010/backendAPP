<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageStorageService
{
    // الأنواع المدعومة
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10MB
    private const RETRY_ATTEMPTS = 2;

    public function uploadPublicImage(UploadedFile $file, string $directory, array $context = []): string
    {
        // ── التحقق المسبق قبل محاولة الرفع ──────────────────────────────────
        $this->validateFile($file, $context);

        $directory = trim($directory, '/');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $path      = $directory . '/' . Str::uuid() . '.' . $extension;
        $mimeType  = $file->getMimeType() ?: 'image/jpeg';

        // قراءة محتوى الملف مرة واحدة
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            Log::error('Failed to read uploaded file', array_merge($context, ['path' => $file->getRealPath()]));
            throw new RuntimeException('فشل قراءة الملف المرفوع. تأكد من صلاحيات التخزين المؤقت.');
        }

        // ── محاولة الرفع مع إعادة المحاولة ───────────────────────────────────
        $lastException = null;
        for ($attempt = 1; $attempt <= self::RETRY_ATTEMPTS; $attempt++) {
            try {
                $uploaded = Storage::disk('supabase')->put($path, $contents, [
                    'visibility'  => 'public',
                    'ContentType' => $mimeType,
                ]);

                if ($uploaded === false) {
                    throw new RuntimeException('Storage::put returned false (no exception thrown).');
                }

                Log::info('Image uploaded successfully', array_merge($context, [
                    'disk'     => 'supabase',
                    'path'     => $path,
                    'size'     => strlen($contents),
                    'attempt'  => $attempt,
                ]));

                return $path;

            } catch (\Throwable $e) {
                $lastException = $e;

                Log::warning("Image upload attempt {$attempt} failed", array_merge($context, [
                    'disk'      => 'supabase',
                    'path'      => $path,
                    'attempt'   => $attempt,
                    'exception' => $e->getMessage(),
                    'class'     => get_class($e),
                ]));

                // انتظر قليلاً قبل إعادة المحاولة
                if ($attempt < self::RETRY_ATTEMPTS) {
                    usleep(300_000); // 300ms
                }
            }
        }

        // فشلت جميع المحاولات — سجّل الخطأ الكامل وارمِ exception واضح
        Log::error('Image upload failed after all retries', array_merge($context, [
            'disk'      => 'supabase',
            'path'      => $path,
            'filename'  => $file->getClientOriginalName(),
            'mime'      => $mimeType,
            'size'      => $file->getSize(),
            'exception' => $lastException?->getMessage(),
            'class'     => $lastException ? get_class($lastException) : null,
            // تشخيص إعدادات التخزين (بدون إظهار الـ secret)
            'storage_key_length'      => strlen(config('filesystems.disks.supabase.key', '')),
            'storage_secret_length'   => strlen(config('filesystems.disks.supabase.secret', '')),
            'storage_endpoint'        => config('filesystems.disks.supabase.endpoint'),
            'storage_bucket'          => config('filesystems.disks.supabase.bucket'),
        ]));

        // تحديد نوع الخطأ لعرض رسالة مناسبة للمستخدم
        $userMessage = $this->getUserFriendlyError($lastException);
        throw new RuntimeException($userMessage, 0, $lastException);
    }

    private function validateFile(UploadedFile $file, array $context): void
    {
        if (!$file->isValid()) {
            Log::warning('Invalid uploaded file', array_merge($context, [
                'error' => $file->getError(),
                'error_message' => $file->getErrorMessage(),
            ]));
            throw new RuntimeException('فشل استقبال الملف: ' . $file->getErrorMessage());
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('حجم الصورة كبير جداً. الحد الأقصى 10 ميغابايت.');
        }

        $mime = $file->getMimeType();
        if ($mime && !in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("نوع الملف غير مدعوم: {$mime}. المدعوم: JPEG, JPG, PNG, WebP, GIF.");
        }
    }

    private function getUserFriendlyError(?\Throwable $e): string
    {
        if (!$e) {
            return 'فشل رفع الصورة. حاول مرة أخرى.';
        }

        $message = strtolower($e->getMessage());

        if (str_contains($message, 'invalidaccesskeyid') || str_contains($message, 'access key')) {
            return 'خطأ في إعدادات التخزين: مفتاح الوصول غير صحيح. تواصل مع الدعم الفني.';
        }

        if (str_contains($message, 'signaturedoesnotmatch') || str_contains($message, 'signature')) {
            return 'خطأ في إعدادات التخزين: توقيع المصادقة غير صحيح. تواصل مع الدعم الفني.';
        }

        if (str_contains($message, 'nosuchbucket') || str_contains($message, 'bucket')) {
            return 'خطأ في إعدادات التخزين: مجلد التخزين غير موجود. تواصل مع الدعم الفني.';
        }

        if (str_contains($message, 'connection') || str_contains($message, 'timeout') || str_contains($message, 'curl')) {
            return 'تعذر الاتصال بخدمة التخزين. تحقق من الاتصال وحاول مرة أخرى.';
        }

        if (str_contains($message, 'accessdenied') || str_contains($message, 'forbidden')) {
            return 'خطأ في الصلاحيات: ليس لديك إذن رفع الملفات. تواصل مع الدعم الفني.';
        }

        return 'فشل رفع الصورة. حاول مرة أخرى.';
    }

    public function publicUrl(string $path): string
    {
        return Storage::disk('supabase')->url($path);
    }

    /**
     * فحص صحة الاتصال بالتخزين
     */
    public function healthCheck(): array
    {
        $testPath = 'health-check/' . Str::uuid() . '.txt';
        $start    = microtime(true);

        try {
            Storage::disk('supabase')->put($testPath, 'ok', ['visibility' => 'public']);
            Storage::disk('supabase')->delete($testPath);

            return [
                'status'        => 'ok',
                'response_ms'   => round((microtime(true) - $start) * 1000),
                'disk'          => 'supabase',
                'bucket'        => config('filesystems.disks.supabase.bucket'),
                'endpoint'      => config('filesystems.disks.supabase.endpoint'),
            ];
        } catch (\Throwable $e) {
            return [
                'status'        => 'error',
                'response_ms'   => round((microtime(true) - $start) * 1000),
                'error'         => $e->getMessage(),
                'error_class'   => get_class($e),
                'disk'          => 'supabase',
                'bucket'        => config('filesystems.disks.supabase.bucket'),
                'endpoint'      => config('filesystems.disks.supabase.endpoint'),
                'key_length'    => strlen(config('filesystems.disks.supabase.key', '')),
                'secret_length' => strlen(config('filesystems.disks.supabase.secret', '')),
            ];
        }
    }
}
