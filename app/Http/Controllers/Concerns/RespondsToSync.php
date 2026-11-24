<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Aksi yang bisa dikirim lewat antrean offline menjawab JSON (untuk klien sinkron),
 * sedangkan pemakaian biasa lewat Inertia tetap redirect + flash.
 */
trait RespondsToSync
{
    protected function done(Request $request, string $message, int $status = 200)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : back()->with('success', $message);
    }

    protected function failed(Request $request, string $message, int $status = 422)
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : back()->with('error', $message);
    }
}
