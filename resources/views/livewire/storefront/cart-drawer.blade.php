<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Modules\Cart\Services\CartService;

new class extends Component
{
    public $cart = null;
    
    // 🔥 نقلنا المنطق من الـ Blade إلى الكلاس (نصيحة GPT)
    public bool $hasItems = false;
    public int $total = 0;

    public function mount(CartService $cartService)
    {
        $this->refreshCart($cartService);
    }

    #[On('cart-updated')]
    public function refreshCart(CartService $cartService)
    {
        $this->cart = $cartService->getCart();
        
        // حساب المتغيرات هنا ليكون الـ Blade نظيفاً وسريعاً
        $this->hasItems = $this->cart && $this->cart->items->isNotEmpty();
        $this->total = $this->hasItems ? $this->cart->items->sum(fn($i) => $i->unit_price * $i->quantity) : 0;
    }

    public function removeItem(CartService $cartService, $itemId)
    {
        $cartService->removeItem($itemId);
        // 🔥 حذفنا الاستدعاء المكرر (نصيحة GPT)
        $this->dispatch('cart-updated');
    }

    public function updateQuantity(CartService $cartService, $itemId, $quantity)
    {
        if ($quantity > 0) {
            $cartService->updateItemQuantity($itemId, $quantity);
            // 🔥 حذفنا الاستدعاء المكرر
            $this->dispatch('cart-updated');
        }
    }
};
?>

<div x-data="{ drawerOpen: false }" @open-cart.window="drawerOpen = true">

    <div x-cloak 
         x-show="drawerOpen" 
         @click="drawerOpen = false" 
         class="fixed inset-0 bg-black bg-opacity-50 z-40 transition-opacity">
    </div>

    <div x-cloak 
         class="fixed inset-y-0 left-0 z-50 w-full max-w-md bg-white shadow-2xl transform transition-transform duration-300 ease-in-out flex flex-col"
         :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'">
        
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">سلة المشتريات</h2>
            <button @click="drawerOpen = false" class="text-gray-400 hover:text-gray-600 outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-4">
            @if(!$hasItems)
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <span class="text-4xl mb-4">🛒</span>
                    <p class="text-gray-500 font-medium">سلتك فارغة حالياً</p>
                    <button @click="drawerOpen = false" class="mt-4 text-blue-600 hover:text-blue-800 font-bold">متابعة التسوق</button>
                </div>
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach($cart->items as $item)
                        <li class="py-4 flex flex-col gap-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900">{{ $item->product_name ?? 'منتج' }}</h3>
                                </div>
                                <p class="text-sm font-bold text-gray-900">{{ number_format($item->unit_price, 0) }} SYP</p>
                            </div>
                            <div class="flex justify-between items-center mt-2">
                                <div class="flex items-center border border-gray-300 rounded">
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="px-2 py-1 text-gray-600 hover:bg-gray-100">-</button>
                                    <span class="px-4 py-1 text-sm font-medium">{{ $item->quantity }}</span>
                                    <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="px-2 py-1 text-gray-600 hover:bg-gray-100">+</button>
                                </div>
                                <button wire:click="removeItem({{ $item->id }})" class="text-red-500 hover:text-red-700 text-sm font-medium flex items-center gap-1">
                                    🗑️ إزالة
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($hasItems)
            <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
                <div class="flex justify-between text-base font-bold text-gray-900 mb-4">
                    <p>الإجمالي</p>
                    <p>{{ number_format($total, 0) }} SYP</p>
                </div>
                <p class="text-xs text-gray-500 mb-4">أجور الشحن تُحسب في خطوة الدفع.</p>
                <a href="{{ route('checkout') }}" class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700">
                    إتمام الطلب
                </a>
            </div>
        @endif
    </div>
</div>