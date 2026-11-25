<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Owner (super-admin) selalu lolos pengecekan hak akses, termasuk izin yang baru ditambahkan di versi mendatang.
        Gate::before(fn ($user, $ability) => $user->hasRole('super-admin') ? true : null);

        // Kebijakan password minimum di SELURUH aplikasi (ganti password, reset oleh admin):
        // minimal 8 karakter, campuran huruf besar/kecil, dan ada angka.
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers();
        });

        // Batas laju default untuk seluruh routes/api.php (60 req/menit per user login, atau per IP bila belum login).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
