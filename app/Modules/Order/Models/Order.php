<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Modules\User\Models\User;

class Order extends Model
{
    use SoftDeletes;

    const STATUS_PENDING_CONFIRMATION = 'pending_conf'; // حالة جديدة
    // 🛡️ حماية Mass Assignment (الأمان أولاً)
    protected $fillable = [
        'user_id',
        'cart_id',            // 👈 أضف هذا
        'idempotency_key',    // 👈 وأضف هذا
        'status',
        'subtotal',
        'shipping_price',
        'discount',
        'total_price',
        'payment_method',
        'currency',
        'shipping_name',
        'shipping_phone',
        'shipping_city',
        'shipping_address_line',
        'notes',
    ];

    // 🧠 التحويلات (Casts) لضمان العمليات الحسابية الصحيحة
    protected $casts = [
        'subtotal' => 'float',
        'shipping_price' => 'float',
        'discount' => 'float',
        'total_price' => 'float',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_COD = 'cod';
    public const PAYMENT_CARD = 'card';

    protected static function booted()
    {
        // 1. قبل الإنشاء: رقم وهمي لمنع تضارب قاعدة البيانات
        static::creating(function ($order) {
            $order->order_number = 'TEMP-' . Str::uuid();
        });

        // 2. بعد الإنشاء: أخذ الـ ID الحقيقي وتحويله لرقم جميل
        static::created(function ($order) {
            $order->order_number = 'ORD-' . now()->year . '-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
            $order->saveQuietly();
        });

        // 3. 🔒 حماية صارمة لرقم الطلب (رمي خطأ عند محاولة التعديل)
        static::updating(function ($order) {
            if ($order->isDirty('order_number')) {
                throw new \Exception('🚨 خطأ أمني: لا يمكن تعديل رقم الفاتورة بعد إنشائها!');
            }
        });
    }
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function vendorOrders()
    {
        // تأكد من المسار الصحيح لمودل VendorOrder لديك
        return $this->hasMany(\App\Modules\Order\Models\VendorOrder::class);
    }
}