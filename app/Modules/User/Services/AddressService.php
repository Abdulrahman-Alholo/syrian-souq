<?
namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Address;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * إضافة عنوان جديد للمستخدم وجعله الافتراضي إذا طلب ذلك
     */
    public function addAddress(User $user, array $addressData, bool $isDefault = false): Address
    {
        return DB::transaction(function () use ($user, $addressData, $isDefault) {
            
            // إذا أراد جعله الافتراضي، نلغي الافتراضية عن عناوينه القديمة
            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            // إذا لم يكن لديه أي عنوان، نجعل هذا العنوان إجبارياً هو الافتراضي
            $isFirstAddress = $user->addresses()->count() === 0;

            return $user->addresses()->create(array_merge($addressData, [
                'is_default' => $isDefault || $isFirstAddress
            ]));
        });
    }
}