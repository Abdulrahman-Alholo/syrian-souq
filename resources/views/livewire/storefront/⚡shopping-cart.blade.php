<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Modules\Cart\Services\CartService;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] class extends Component
{
    public $cartItems = [];
    public float $subtotal = 0;
    public int $itemsCount = 0;
    public string $errorMessage = '';

    public function mount(CartService $cartService)
    {
        $this->loadCart($cartService);
    }

    public function loadCart(CartService $cartService)
    {
        // 🔥 جلب الداتا بضربة واحدة من السيرفس (N+1 Fixed)
        $cart = $cartService->getCartWithItems();
        $totals = $cartService->calculateTotals($cart);

        $this->cartItems = $cart->items;
        $this->subtotal = $totals['subtotal'];
        $this->itemsCount = $totals['items_count'];
    }

    public function updateQuantity(CartService $cartService, int $itemId, int $newQuantity)
    {
        $this->errorMessage = '';

        try {
            // اللوجيك كله صار بالسيرفس
            $cartService->updateItemQuantity($itemId, $newQuantity);
            $this->loadCart($cartService); 
            $this->dispatch('cart-updated'); 
        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = 'حدث خطأ غير متوقع.';
            Log::error('Cart Update: ' . $e->getMessage());
        }
    }

    public function removeItem(CartService $cartService, int $itemId)
    {
        try {
            // الحذف الآمن من السيرفس
            $cartService->removeItem($itemId);
            $this->loadCart($cartService);
            $this->dispatch('cart-updated');
        } catch (\DomainException $e) {
             $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
             $this->errorMessage = 'حدث خطأ أثناء الحذف.';
             Log::error('Cart Remove: ' . $e->getMessage());
        }
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">سلة المشتريات</h1>

    @if ($errorMessage)
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ $errorMessage }}</span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" wire:click="$set('errorMessage', '')">
                <svg class="fill-current h-6 w-6 text-red-500 cursor-pointer" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
            </span>
        </div>
    @endif

    @if($itemsCount > 0)
        <div class="flex flex-col lg:flex-row gap-8">
            
            <div class="lg:w-2/3">
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <ul role="list" class="divide-y divide-gray-200">
                        @foreach($cartItems as $item)
                            <li class="p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-24 h-24 bg-gray-200 rounded-md overflow-hidden">
                                         @if($item->variant->product->hasMedia('product_thumbnail'))
                                            <img src="{{ $item->variant->product->getFirstMediaUrl('product_thumbnail') }}" alt="{{ $item->variant->product->name_ar }}" class="w-full h-full object-center object-cover">
                                        @else
                                            <div class="flex items-center justify-center w-full h-full text-gray-500 text-xs">لا توجد صورة</div>
                                        @endif
                                    </div>

                                    <div class="ml-6 flex-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <a href="#">{{ $item->variant->product->name_ar ?? 'منتج غير محدد' }}</a>
                                            </h3>
                                            <p class="text-lg font-semibold text-gray-900">{{ number_format($item->variant->final_price, 0) }} SYP</p>
                                        </div>

                                        <div class="mt-1 text-sm text-gray-500">
                                            @foreach($item->variant->attributeValues as $attrValue)
                                                <span class="inline-block mr-2">{{ $attrValue->attribute->name_ar }}: <span class="font-medium text-gray-700">{{ $attrValue->value_ar }}</span></span>
                                            @endforeach
                                            @if($item->variant->sku)
                                                <span class="inline-block mr-2 text-xs text-gray-400">(SKU: {{ $item->variant->sku }})</span>
                                            @endif
                                        </div>

                                        <div class="mt-4 flex items-center justify-between">
                                            <div class="flex items-center border border-gray-300 rounded-md">
                                                <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="px-3 py-1 text-gray-600 hover:bg-gray-100 transition focus:outline-none" wire:loading.attr="disabled" wire:target="updateQuantity">-</button>
                                                <span class="px-4 py-1 text-gray-900 font-medium">{{ $item->quantity }}</span>
                                                <button wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="px-3 py-1 text-gray-600 hover:bg-gray-100 transition focus:outline-none" wire:loading.attr="disabled" wire:target="updateQuantity">+</button>
                                            </div>

                                            <button wire:click="removeItem({{ $item->id }})" class="text-red-500 hover:text-red-700 text-sm font-medium focus:outline-none flex items-center" wire:loading.attr="disabled" wire:target="removeItem">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                إزالة
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="lg:w-1/3">
                <div class="bg-gray-50 rounded-lg shadow px-6 py-8">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">ملخص الطلب</h2>
                    
                    <div class="flow-root">
                        <dl class="-my-4 text-sm divide-y divide-gray-200">
                            <div class="py-4 flex items-center justify-between">
                                <dt class="text-gray-600">عدد المنتجات</dt>
                                <dd class="font-medium text-gray-900">{{ $itemsCount }}</dd>
                            </div>
                            <div class="py-4 flex items-center justify-between">
                                <dt class="text-base font-bold text-gray-900">المجموع (غير شامل الشحن)</dt>
                                <dd class="text-xl font-bold text-gray-900">{{ number_format($subtotal, 0) }} SYP</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-8">
                        <a href="/checkout" class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            متابعة للدفع
                        </a>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="/" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            أو الاستمرار بالتسوق <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    @else
        <div class="text-center py-16 bg-white shadow rounded-lg">
            <svg class="mx-auto h-24 w-24 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">سلتك فارغة حالياً!</h3>
            <p class="mt-1 text-sm text-gray-500">يبدو أنك لم تقم بإضافة أي منتجات بعد.</p>
            <div class="mt-6">
                <a href="/" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    ابدأ التسوق الآن
                </a>
            </div>
        </div>
    @endif
</div>