<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Modules\Order\Observers\VendorOrderObserver;
class VendorOrder extends Model
{
    protected $guarded = ['id'];

    // العلاقات
    public function order() { return $this->belongsTo(Order::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function vendor() { return $this->belongsTo(\App\Modules\Vendor\Models\Vendor::class); }

    /**
     * 🧠 مسارات الحالة المسموحة (State Machine Rules)
     */
    public function getValidNextStatuses($isAdmin = false): array
    {
        $currentStatus = $this->getOriginal('status') ?? $this->status;

        if ($isAdmin) {
            return match ($currentStatus) {
                'pending' => ['processing', 'cancelled'],
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered', 'failed_delivery', 'returned'],
                'delivered', 'cancelled', 'returned', 'failed_delivery' => [],
                default => [],
            };
        }

        // صلاحيات التاجر
        return match ($currentStatus) {
            'pending' => ['processing', 'cancelled'], 
            'processing' => ['shipped'],              
            'shipped' => [], // التاجر يقف هنا 🛑
            'delivered', 'cancelled', 'returned', 'failed_delivery' => [],
            default => [],
        };
    }

    /**
     * 🛡️ Guard: الحماية الحديدية في الباك إند
     */
    protected static function booted()
    {
        static::updating(function (VendorOrder $model) {
            if ($model->isDirty('status')) {
                $newStatus = $model->status;
                $oldStatus = $model->getOriginal('status') ?? $model->status;
                
                if ($newStatus === $oldStatus) return;

                $allowedStatuses = $model->getValidNextStatuses(isAdmin: true); 

                if (!in_array($newStatus, $allowedStatuses)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'status' => "انتقال غير مسموح من [$oldStatus] إلى [$newStatus]"
                    ]);
                }
            }
        });
    }
}