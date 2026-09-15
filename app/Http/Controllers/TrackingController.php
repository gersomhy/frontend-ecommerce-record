<?php

namespace App\Http\Controllers;

use App\Support\CatatAktivitas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /**
     * Catat durasi waktu pengunjung (dwell time) melihat seksi tertentu di website.
     * Menerima payload batch (array 'events') maupun single object.
     */
    public function recordSectionDwell(Request $request): JsonResponse
    {
        $rawEvents = $request->input('events');

        if (! is_array($rawEvents)) {
            // Jika dikirim sebagai objek tunggal
            $rawEvents = [$request->all()];
        }

        $recordedCount = 0;

        foreach ($rawEvents as $eventData) {
            if (! is_array($eventData)) {
                continue;
            }

            $sectionId = trim((string) ($eventData['section_id'] ?? ''));
            $sectionLabel = trim((string) ($eventData['section_label'] ?? ''));
            $pageName = trim((string) ($eventData['page_name'] ?? 'Halaman Toko'));
            $pageUrl = trim((string) ($eventData['page_url'] ?? ''));
            $duration = (int) ($eventData['duration_seconds'] ?? 0);

            // Filter validasi: id seksi wajib ada, durasi minimal 3 detik (anti-spam scroll cepat), dan maksimal 1 jam
            if ($sectionId === '' || $duration < 3 || $duration > 3600) {
                continue;
            }

            if ($sectionLabel === '') {
                $sectionLabel = ucwords(str_replace('_', ' ', $sectionId));
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
                    'section_id'         => $sectionId,
                    'section_label'      => $sectionLabel,
                    'page_name'          => $pageName,
                    'page_url'           => $pageUrl,
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