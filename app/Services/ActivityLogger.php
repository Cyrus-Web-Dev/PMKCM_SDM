<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;

class ActivityLogger
{
    /**
     * Catat satu entri audit trail: siapa melakukan apa, terhadap data apa, kapan, dari IP/perangkat mana,
     * dan (bila ada) apa saja yang berubah ($properties, mis. hasil changes()).
     */
    public static function log(string $action, string $description, ?Model $subject = null, ?int $userId = null, ?array $properties = null): void
    {
        ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => RequestFacade::ip(),
            'user_agent' => mb_substr((string) RequestFacade::userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Perubahan atribut model SETELAH save()/update(): ['kolom' => ['dari' => lama, 'ke' => baru]].
     * Kolom sensitif/terenkripsi sebaiknya dimasukkan ke $except supaya tidak tercatat.
     */
    public static function changes(Model $model, array $except = []): array
    {
        $skip = array_merge(['updated_at', 'created_at', 'remember_token', 'password'], $except);
        $previous = $model->getPrevious();
        $out = [];

        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            $out[$key] = ['dari' => $previous[$key] ?? null, 'ke' => $value];
        }

        return $out;
    }
}
