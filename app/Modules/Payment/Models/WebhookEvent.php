<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    use Prunable; // 🧹 تفعيل ميزة التنظيف التلقائي

    protected $fillable = [
        'gateway',
        'event_id',
        'event_type',
        'payment_id',
        'payload',
        'status',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * 🟢 تحديد السجلات التي يجب حذفها (أقدم من 30 يوم)
     * سيتم تشغيلها يومياً عبر الـ Schedule (php artisan model:prune)
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(30));
    }
}