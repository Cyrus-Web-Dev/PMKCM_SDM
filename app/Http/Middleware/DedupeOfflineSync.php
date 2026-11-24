<?php

namespace App\Http\Middleware;

use App\Models\OfflineSync;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiriman dari antrean offline membawa header X-Offline-Id. Bila ID itu sudah pernah BERHASIL diproses
 * (mis. respons hilang di jalan lalu dikirim ulang), kirim jawaban sukses tanpa memproses ulang
 * -> tidak ada absensi/laporan ganda.
 */
class DedupeOfflineSync
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Offline-Id');
        $user = $request->user();

        if (! $id || ! $user) {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9\-]{8,64}$/', $id)) {
            return response()->json(['message' => 'ID sinkronisasi tidak valid.'], 422);
        }

        if (OfflineSync::where('uuid', $id)->exists()) {
            return response()->json(['message' => 'Data ini sudah pernah disinkronkan.', 'duplicate' => true], 200);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            try {
                OfflineSync::create(['uuid' => $id, 'user_id' => $user->id, 'created_at' => now()]);
            } catch (QueryException) {
                // balapan dengan permintaan identik: abaikan, unique constraint sudah menjaga
            }
        }

        return $response;
    }
}
