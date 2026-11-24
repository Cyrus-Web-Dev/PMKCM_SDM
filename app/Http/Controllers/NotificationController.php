<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Notifikasi di dalam aplikasi (ikon lonceng). Setiap pengguna hanya melihat notifikasinya sendiri. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Notifications/Index', [
            'notifications' => AppNotification::where('user_id', $request->user()->id)
                ->orderByDesc('created_at')->orderByDesc('id')->paginate(20)
                ->through(fn (AppNotification $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'body' => $n->body,
                    'read' => (bool) $n->read_at,
                    'at' => $n->created_at->format('d/m/Y H:i'),
                ]),
        ]);
    }

    /** Tandai dibaca lalu buka halaman tujuannya (hanya alamat internal). */
    public function open(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        $url = $notification->url;

        return redirect($url && str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
