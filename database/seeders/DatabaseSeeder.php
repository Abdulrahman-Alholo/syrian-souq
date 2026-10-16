<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Modules\User\Models\User;
use App\Modules\Vendor\Models\Vendor;
use App\Modules\Catalog\Models\Category;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ==========================================
        // 1. إنشاء حساب الإدارة العليا (Super Admin)
        // ==========================================
        User::create([
            'name' => 'المدير العام',
            'email' => 'admin@souq.com',
            'phone' => '0930000000',
            'password' => Hash::make('password'), // كلمة السر: password
            'account_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 2. إنشاء حساب تاجر تجريبي مع متجره (Vendor)
        // ==========================================
        $vendorUser = User::create([
            'name' => 'تاجر الأناقة',
            'email' => 'vendor@souq.com',
            'phone' => '0940000000',
            'password' => Hash::make('password'), // كلمة السر: password
            'account_type' => 'vendor',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // نربط التاجر بمتجره ونعطيه حالة "مقبول" لكي يستطيع تسجيل الدخول
        Vendor::create([
            'user_id' => $vendorUser->id,
            'store_name' => 'متجر الأناقة السورية',
            'store_slug' => 'syrian-elegance',
            'description' => 'متجر متخصص ببيع أفضل الملابس بجودة عالية.',
            'city' => 'دمشق',
            'status' => 'approved', 
            'is_active' => true,
            'base_currency' => 'SYP',
            'commission_rate' => 5.00,
            'approved_at' => now(),
        ]);

        // ==========================================
        // 3. إنشاء الأقسام الأساسية للمتجر (Categories)
        // ==========================================
        $categories = [
            ['ar' => 'ملابس رجالية', 'en' => 'Mens Clothing'],
            ['ar' => 'ملابس نسائية', 'en' => 'Womens Clothing'],
            ['ar' => 'أحذية', 'en' => 'Shoes'],
            ['ar' => 'إكسسوارات', 'en' => 'Accessories'],
        ];

        foreach ($categories as $cat) {
            Category::create([
                'name_ar' => $cat['ar'],
                'name_en' => $cat['en'],
                'slug' => Str::slug($cat['en']), // الـ Slug دائماً بالإنكليزي لروابط نظيفة
                'is_active' => true,
            ]);
        }
    }
}