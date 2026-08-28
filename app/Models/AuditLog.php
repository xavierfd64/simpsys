<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['tenant_id', 'user_id', 'action', 'description', 'metadata', 'ip_address'])]
class AuditLog extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $action, array $attributes = []): self
    {
        return static::create(array_merge([
            'action' => $action,
            'ip_address' => request()->ip(),
        ], $attributes));
    }
}
