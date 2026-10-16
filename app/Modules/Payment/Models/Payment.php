<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Order\Models\Order;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'method',
        'status',
        'amount',
        'currency',
        'idempotency_key',
        'transaction_id',
        'gateway_reference',
        'meta',
        'response_payload',
    ];

    // 🟢 تحويل حقول الـ JSON من الداتا بيز إلى مصفوفات برمجية والعكس تلقائياً
    protected $casts = [
        'meta' => 'array',
        'response_payload' => 'array',
    ];

    // علاقة الدفع بالطلب
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}