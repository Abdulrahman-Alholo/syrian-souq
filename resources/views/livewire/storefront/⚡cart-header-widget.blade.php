<?php

use Livewire\Component;
use Livewire\Attributes\On; 
use App\Modules\Cart\Services\CartService;

new class extends Component
{
    public int $count = 0;

    public function mount(CartService $cartService)
    {
        $this->updateCartCount($cartService);
    }

    #[On('cart-updated')] 
    public function updateCartCount(CartService $cartService)
    {
        $cart = $cartService->getCart();
        $totals = $cartService->calculateTotals($cart);
        $this->count = $totals['items_count']; 
    }
};
?>

<div class="relative inline-block" x-data>
    <button @click="$dispatch('open-cart')" class="flex items-center text-gray-700 hover:text-blue-600 transition-colors focus:outline-none">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        
        @if($count > 0)
            <span class="absolute -top-1 -right-2 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full shadow-sm animate-bounce-short">
                {{ $count }}
            </span>
        @endif
    </button>
</div>