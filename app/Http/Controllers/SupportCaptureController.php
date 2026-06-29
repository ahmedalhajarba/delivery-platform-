<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportCaptureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image_data' => 'required|string',
            'page_url' => 'nullable|string|max:2000',
            'page_title' => 'nullable|string|max:255',
            'captured_at_client' => 'nullable|string|max:100',
            'viewport' => 'nullable|string|max:100',
        ]);

        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $data['image_data'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Unsupported image format.',
            ], 422);
        }

        $rawBase64 = substr($data['image_data'], strpos($data['image_data'], ',') + 1);
        $binary = base64_decode($rawBase64, true);

        if ($binary === false) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid image payload.',
            ], 422);
        }

        if (strlen($binary) > (8 * 1024 * 1024)) {
            return response()->json([
                'ok' => false,
                'message' => 'Image exceeds max size (8MB).',
            ], 422);
        }

        $mime = 'image/png';
        if (str_starts_with($data['image_data'], 'data:image/jpeg') || str_starts_with($data['image_data'], 'data:image/jpg')) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($data['image_data'], 'data:image/webp')) {
            $mime = 'image/webp';
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $now = now();
        $folder = 'support-captures/' . $now->format('Y/m');
        $filename = 'capture_' . $now->format('Ymd_His') . '_' . auth()->id() . '_' . Str::lower(Str::random(8)) . '.' . $extension;
        $path = $folder . '/' . $filename;

        Storage::disk('public')->put($path, $binary);

        $fileHash = hash('sha256', $binary);
        $serverTimestamp = $now->toIso8601String();
        $signatureBase = implode('|', [
            (string) auth()->id(),
            $serverTimestamp,
            (string) ($data['page_url'] ?? ''),
            $path,
            $fileHash,
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]);
        $signature = hash_hmac('sha256', $signatureBase, (string) config('app.key'));

        $meta = [
            'user_id' => auth()->id(),
            'captured_at_server' => $serverTimestamp,
            'captured_at_client' => $data['captured_at_client'] ?? null,
            'page_url' => $data['page_url'] ?? null,
            'page_title' => $data['page_title'] ?? null,
            'viewport' => $data['viewport'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'hash_sha256' => $fileHash,
            'signature_hmac' => $signature,
        ];
        Storage::disk('public')->put($path . '.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'ok' => true,
            'capture_path' => $path,
            'capture_url' => route('support.captures.view', ['path' => $path]),
            'capture_hash' => $fileHash,
            'capture_signature' => $signature,
            'capture_timestamp' => $serverTimestamp,
        ]);
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string|max:255',
        ]);

        $path = ltrim((string) $validated['path'], '/\\');

        if (str_contains($path, '..') || !str_starts_with($path, 'support-captures/')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            abort(404);
        }

        $absolutePath = storage_path('app/public/' . $path);
        $mime = @mime_content_type($absolutePath) ?: 'application/octet-stream';

        return Response::file($absolutePath, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
