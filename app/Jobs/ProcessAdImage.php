<?php

namespace App\Jobs;

use App\Models\AdImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProcessAdImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $adImage;

    /**
     * Create a new job instance.
     */
    public function __construct(AdImage $adImage)
    {
        $this->adImage = $adImage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!$this->adImage->image_path) {
                return;
            }

            // Get original image from storage
            $imageContent = Storage::disk('supabase')->get($this->adImage->image_path);
            if (!$imageContent) {
                return;
            }

            // Initialize Image Manager with GD driver (common for most hosts)
            $manager = new ImageManager(new Driver());

            // Read image from binary content
            $image = $manager->read($imageContent);

            // Resize to thumbnail (Max width 400px, maintain aspect ratio)
            $image->resize(400, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Convert to binary
            $thumbnailBinary = $image->toJpeg(80)->toString();

            // Generate thumbnail path
            $thumbnailPath = 'thumbnails/' . basename($this->adImage->image_path);

            // Store thumbnail in Supabase
            Storage::disk('supabase')->put($thumbnailPath, $thumbnailBinary);

            // Update database record
            $this->adImage->update([
                'thumbnail_path' => $thumbnailPath
            ]);

        }
        catch (\Exception $e) {
            Log::error('ProcessAdImage Failed: ' . $e->getMessage());
        // Optionally re-throw to retry
        }
    }
}
