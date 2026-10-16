<?php

use Livewire\Component;
use App\Modules\Cart\Services\CartService;

new class extends Component
{
    public int $variantId;
    public int $quantity = 1; 
    
    // للتحكم بالواجهة
    public bool $isAvailable = true; 
    public string $buttonText = 'أضف للسلة';
    public string $statusMessage = '';
    public bool $isError = false;

    // دالة التحميل الأولية (تستقبل الداتا من الـ Blade)
    public function mount(int $variantId, int $availableStock)
    {
        $this->variantId = $variantId;
        
        // إذا كان المخزون 0 من البداية، نعطل الزر
        if ($availableStock <= 0) {
            $this->isAvailable = false;
            $this->buttonText = 'نفدت الكمية';
        }
    }

    // دالة الإضافة للسلة
    public function addToCart(CartService $cartService)
    {
        if (!$this->isAvailable) return;

        try {
            $cartService->addItem($this->variantId, $this->quantity);
            
            $this->isError = false;
            $this->statusMessage = 'تمت الإضافة بنجاح!';
            
            // 📢 إطلاق حدث لتحديث عداد السلة بالنافبار
            $this->dispatch('cart-updated');
            
            // 🚀 إطلاق حدث لفتح السلة الجانبية فوراً أمام العميل
            $this->dispatch('open-cart');
            
        } catch (Exception $e) {
            $this->isError = true;
            $this->statusMessage = $e->getMessage();
        }
    }
};
?>

<div class="relative inline-block w-full sm:w-auto">
    <button 
        wire:click="addToCart"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-75 cursor-wait"
        @disabled(!$isAvailable)
        class="w-full sm:w-auto px-6 py-3 font-semibold text-white transition-colors duration-200 rounded-lg shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2
            @if($isAvailable) bg-blue-600 hover:bg-blue-700 focus:ring-blue-500 @else bg-gray-400 cursor-not-allowed @endif"
    >
        <span wire:loading.remove wire:target="addToCart">
            <svg xmlns="http://www.w3.org/2000/svg" class="inline-block w-5 h-5 ml-1 -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            {{ $buttonText }}
        </span>
        
        <span wire:loading wire:target="addToCart">
            جاري الإضافة...
        </span>
    </button>

    @if ($statusMessage)
        <div 
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition.opacity
            class="absolute left-0 right-0 z-10 w-full p-2 mt-2 text-sm text-center rounded-md shadow-sm sm:w-64 sm:right-auto sm:left-1/2 sm:-translate-x-1/2
            @if($isError) bg-red-100 text-red-700 border border-red-200 @else bg-green-100 text-green-700 border border-green-200 @endif"
        >
            {{ $statusMessage }}
        </div>
    @endif
</div>