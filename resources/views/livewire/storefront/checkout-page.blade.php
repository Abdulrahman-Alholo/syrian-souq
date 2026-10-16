<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Modules\Cart\Services\CartService;
use App\Modules\Cart\Services\CheckoutService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

new #[Layout('layouts.app')] class extends Component
{
    // بيانات الشحن
    public string $shipping_name = '';
    public string $shipping_phone = '';
    public string $shipping_city = '';
    public string $shipping_address_line = '';
    public string $notes = '';
    public string $payment_method = 'cod';

    // ملخص السلة
    public float $subtotal = 0;
    public int $itemsCount = 0;
    public string $errorMessage = '';

    // مصفوفة المدن لسهولة الصيانة
    public array $cities = [
        'دمشق', 'حلب', 'حمص', 'اللاذقية', 'حماة', 'طرطوس'
    ];

    public function mount(CartService $cartService)
    {
        $cart = $cartService->getCart();
        
        if ($cart->items()->count() === 0) {
            return redirect()->route('cart');
        }

        $totals = $cartService->calculateTotals($cart);
        $this->subtotal = $totals['subtotal'];
        $this->itemsCount = $totals['items_count'];

        if (auth()->check()) {
            $this->shipping_name = auth()->user()->name ?? '';
        }
    }

    public function placeOrder(CartService $cartService, CheckoutService $checkoutService)
    {
        $this->validate([
            'shipping_name'         => 'required|string|max:255',
            'shipping_phone'        => 'required|string|max:20',
            'shipping_city'         => 'required|string|max:100',
            'shipping_address_line' => 'required|string|max:500',
        ], [
            'required' => 'هذا الحقل مطلوب لإتمام الطلب.',
        ]);

        $this->errorMessage = '';

        try {
            $cart = $cartService->getCart();

            $order = $checkoutService->processCheckout(
                cart: $cart,
                shippingData: [
                    'shipping_name'         => $this->shipping_name,
                    'shipping_phone'        => $this->shipping_phone,
                    'shipping_city'         => $this->shipping_city,
                    'shipping_address_line' => $this->shipping_address_line,
                    'notes'                 => $this->notes,
                ],
                paymentMethod: $this->payment_method,
                idempotencyKey: 'checkout_cart_' . $cart->id . '_' . Str::random(8)
            );

            // نستخدم الـ Session لتأمين طلبات الزوار (Guest Checkout Security)
            session()->flash('success_order_id', $order->id);
            $this->dispatch('cart-updated'); 
            
            return redirect()->route('checkout.success');

        } catch (\DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->errorMessage = 'حدث خطأ غير متوقع أثناء معالجة الطلب. يرجى المحاولة مرة أخرى.';
            Log::error('Checkout Error: ' . $e->getMessage());
        }
    }
};
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">إتمام الطلب</h1>

    @if ($errorMessage)
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ $errorMessage }}</span>
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-8">
        
        <div class="lg:w-2/3">
            <div class="bg-white shadow overflow-hidden sm:rounded-md p-6">
                <h2 class="text-xl font-medium text-gray-900 mb-6">تفاصيل التوصيل</h2>
                
                <form id="checkout-form" wire:submit.prevent="placeOrder" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">الاسم الكامل *</label>
                            <input type="text" wire:model.blur="shipping_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('shipping_name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">رقم الهاتف *</label>
                            <input type="text" wire:model.blur="shipping_phone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('shipping_phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">المدينة / المحافظة *</label>
                        <select wire:model.blur="shipping_city" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">اختر المدينة...</option>
                            @foreach($cities as $city)
                                <option value="{{ $city }}">{{ $city }}</option>
                            @endforeach
                        </select>
                        @error('shipping_city') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">العنوان التفصيلي (المنطقة، الشارع، البناء) *</label>
                        <textarea wire:model.blur="shipping_address_line" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('shipping_address_line') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">ملاحظات إضافية (اختياري)</label>
                        <textarea wire:model.blur="notes" rows="2" placeholder="أوقات التوصيل المفضلة، تفاصيل إضافية..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">طريقة الدفع</h3>
                        <div class="flex items-center">
                            <input id="payment_cod" wire:model="payment_method" type="radio" value="cod" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label for="payment_cod" class="ml-3 block text-sm font-medium text-gray-700">
                                الدفع عند الاستلام (COD)
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:w-1/3">
            <div class="bg-gray-50 rounded-lg shadow px-6 py-8 sticky top-6">
                <h2 class="text-lg font-medium text-gray-900 mb-6">ملخص الطلب</h2>
                
                <div class="flow-root">
                    <dl class="-my-4 text-sm divide-y divide-gray-200">
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">عدد المنتجات</dt>
                            <dd class="font-medium text-gray-900">{{ $itemsCount }}</dd>
                        </div>
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">المجموع الفرعي</dt>
                            <dd class="font-medium text-gray-900">{{ number_format($subtotal, 0) }} SYP</dd>
                        </div>
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-gray-600">رسوم الشحن</dt>
                            <dd class="font-medium text-green-600">سيتم تحديدها لاحقاً</dd>
                        </div>
                        <div class="py-4 flex items-center justify-between">
                            <dt class="text-base font-bold text-gray-900">الإجمالي المتوقع</dt>
                            <dd class="text-xl font-bold text-gray-900">{{ number_format($subtotal, 0) }} SYP</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-8">
                    <button 
                        type="submit"
                        form="checkout-form"
                        wire:loading.attr="disabled"
                        wire:target="placeOrder"
                        class="w-full flex justify-center items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="placeOrder">تأكيد الطلب 🛒</span>
                        <span wire:loading wire:target="placeOrder">جاري معالجة الطلب...</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>