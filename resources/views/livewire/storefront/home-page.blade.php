<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Modules\Catalog\Models\Product;

new #[Layout('layouts.app')] class extends Component
{
    public $products = [];

    public function mount()
    {
        // جلب المنتجات الفعالة مع النسخة الأولى لكل منتج (عشان السعر وزر الإضافة)
        $this->products = Product::active()
            ->with(['variants' => function($q) {
                $q->inStock();
            }, 'media'])
            ->get();
    }
};
?>

<div class="bg-gray-50 min-h-screen">
    <div class="bg-blue-600 pb-24">
        <div class="max-w-7xl mx-auto py-16 px-4 sm:py-24 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl font-extrabold text-white sm:text-5xl md:text-6xl">
                مرحباً بك في <span class="text-blue-200">سوق سوريا</span>
            </h1>
            <p class="mt-4 max-w-2xl mx-auto text-xl text-blue-100">
                تسوق أفضل المنتجات من متاجرك المفضلة بكل سهولة وأمان.
            </p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-24 pb-16">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            
            @forelse($products as $product)
                @php 
                    // نأخذ أول نسخة متوفرة من المنتج لعرض السعر الأساسي
                    $firstVariant = $product->variants->first(); 
                @endphp

                <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300 flex flex-col">
                    <div class="relative h-64 bg-gray-200 group">
                        @if($product->hasMedia('product_thumbnail'))
                            <img src="{{ $product->getFirstMediaUrl('product_thumbnail') }}" alt="{{ $product->name_ar }}" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="flex items-center justify-center w-full h-full text-gray-400">بدون صورة</div>
                        @endif
                        
                        @if($firstVariant && $firstVariant->is_discounted)
                            <span class="absolute top-2 right-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-md">تخفيض!</span>
                        @endif
                    </div>

                    <div class="p-5 flex flex-col flex-grow">
                        <h3 class="text-lg font-bold text-gray-900 mb-1 truncate">{{ $product->name_ar }}</h3>
                        <p class="text-sm text-gray-500 mb-4 line-clamp-2">{{ $product->description ?? 'لا يوجد وصف متاح لهذا المنتج حالياً.' }}</p>
                        
                        <div class="mt-auto flex items-center justify-between mb-4">
                            @if($firstVariant)
                                <div>
                                    <span class="text-xl font-extrabold text-gray-900">{{ number_format($firstVariant->final_price, 0) }} SYP</span>
                                    @if($firstVariant->is_discounted)
                                        <span class="text-sm text-gray-400 line-through block">{{ number_format($firstVariant->price, 0) }} SYP</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-sm text-red-500 font-medium">غير متوفر حالياً</span>
                            @endif
                        </div>

                        @if($firstVariant)
                            <livewire:storefront.add-to-cart-button 
                                :variant-id="$firstVariant->id" 
                                :available-stock="$firstVariant->available_stock" 
                            />
                        @else
                            <button disabled class="w-full bg-gray-300 text-gray-500 py-2 rounded-lg cursor-not-allowed font-medium">نفدت الكمية</button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center bg-white rounded-xl shadow-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">لا يوجد منتجات</h3>
                    <p class="mt-1 text-sm text-gray-500">لم يقم التجار بإضافة أي منتجات حتى الآن.</p>
                </div>
            @endforelse

        </div>
    </div>
</div>