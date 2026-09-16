<?php

namespace App\Http\Controllers;

use App\Support\CatatAktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Catat durasi waktu pengunjung (dwell time) melihat seksi tertentu di website.
     * Menerima payload batch (array 'items' atau 'events') maupun single object.
     */
    public function recordSectionDwell(Request $request): JsonResponse
    {
        $rawEvents = $request->input('items') ?? $request->input('events') ?? $request->all();

        // Jika single object (ada section atau section_id langsung di root)
        if (is_array($rawEvents) && (isset($rawEvents['section']) || isset($rawEvents['section_id']))) {
            $rawEvents = [$rawEvents];
        }

        if (! is_array($rawEvents)) {
            return response()->json(['status' => 'ignored', 'reason' => 'invalid_payload'], 400);
        }

        $recordedCount = 0;

        foreach ($rawEvents as $eventData) {
            if (! is_array($eventData)) {
                continue;
            }

            $sectionId    = trim((string) ($eventData['section'] ?? $eventData['section_id'] ?? ''));
            $sectionLabel = trim((string) ($eventData['label'] ?? $eventData['section_label'] ?? ''));
            $pageUrl      = trim((string) ($eventData['page'] ?? $eventData['page_url'] ?? ''));
            $pageName     = trim((string) ($eventData['page_name'] ?? ($pageUrl ?: 'Halaman Toko')));
            $duration     = (int) ($eventData['seconds'] ?? $eventData['duration_seconds'] ?? 0);

            // Filter validasi: id seksi wajib ada, durasi minimal 3 detik (anti-spam scroll cepat), dan maksimal 1 jam
            if ($sectionId === '' || $duration < 3 || $duration > 3600) {
                continue;
            }

            if ($sectionLabel === '') {
                $sectionLabel = ucwords(str_replace(['_', '-'], ' ', $sectionId));
            }

            // Format durasi agar ramah dibaca manusia
            if ($duration < 60) {
                $durationFormatted = $duration . ' detik';
            } else {
                $menit = floor($duration / 60);
                $detik = $duration % 60;
                $durationFormatted = $detik > 0 ? "{$menit}m {$detik}d" : "{$menit} menit";
            }

            $keterangan = "Pengunjung melihat seksi \"{$sectionLabel}\" di {$pageName} selama {$durationFormatted}";

            CatatAktivitas::tulis(
                grup: 'evaluasi_web',
                keterangan: $keterangan,
                subjek: null,
                properti: [
                    'section'            => $sectionId,
                    'section_id'         => $sectionId,
                    'label'              => $sectionLabel,
                    'section_label'      => $sectionLabel,
                    'page'               => $pageUrl,
                    'page_name'          => $pageName,
                    'page_url'           => $pageUrl,
                    'seconds'            => $duration,
                    'duration_seconds'   => $duration,
                    'duration_formatted' => $durationFormatted,
                ],
                peristiwa: 'dwell'
            );

            $recordedCount++;
        }

        return response()->json([
            'status'   => 'success',
            'recorded' => $recordedCount,
        ]);
    }
}