<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * back() milik Laravel memakai header Referer. Di sebagian hosting/proxy header itu hilang atau dipotong, sehingga
 * back() jatuh ke "/" lalu berujung di dashboard setelah tambah/ubah/hapus data. Klien (app.js) mengirim alamat halaman
 * aktif lewat header X-Current-Url; di sini nilainya dipakai sebagai Referer sehingga back() selalu kembali ke halaman yang sama.
 */
class UseCurrentUrlAsReferer
{
    public function handle(Request $request, Closure $next): Response
    {
        $current = $request->headers->get('X-Current-Url');

        // Hanya alamat internal berbentuk path ("/xxx"), bukan URL penuh atau "//host".
        if ($current && strlen($current) < 2000 && str_starts_with($current, '/') && ! str_starts_with($current, '//')) {
            $request->headers->set('referer', $current);
        }

        return $next($request);
    }
}
