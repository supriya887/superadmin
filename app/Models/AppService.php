<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppService extends Model
{
    use HasFactory;

    protected $connection = 'pgsql_product';

    protected $table = 'apps';

    protected $fillable = [
        'name',
        'image',
        'status',
    ];

    protected $appends = [
        'image_url',
    ];

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'plan_app', 'app_id', 'plan_id');
    }

    /**
     * DB may store "apps/icons/x.png" or a legacy prefixed path.
     */
    public function imageRelativePath(): string
    {
        $raw = trim((string) ($this->image ?? ''));
        if ($raw === '') {
            return '';
        }

        return ltrim(preg_replace('#^(storage/app/public/|public/storage/|storage/)+#', '', $raw) ?? $raw, '/');
    }

    public function getImageUrlAttribute(): ?string
    {
        $rel = $this->imageRelativePath();
        if ($rel === '') {
            return null;
        }

        $this->ensurePublicMirror();

        return rtrim((string) config('app.url'), '/') . '/storage/' . $rel;
    }

    /**
     * Copy storage/app/public/{rel} → public/storage/{rel} when missing.
     */
    public function ensurePublicMirror(): bool
    {
        $rel = $this->imageRelativePath();
        if ($rel === '') {
            return false;
        }

        $src = storage_path('app/public/' . $rel);
        $dst = public_path('storage/' . $rel);

        if (is_file($dst) && filesize($dst) > 0) {
            return true;
        }

        if (!is_file($src) || filesize($src) <= 0) {
            return false;
        }

        $dir = dirname($dst);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return (bool) @copy($src, $dst);
    }
}
