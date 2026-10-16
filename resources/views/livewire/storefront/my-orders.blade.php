<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Collection;

new #[Layout('layouts.app')] class extends Component
{
    public Collection $orders;

    public function mount()
    {
        $this->orders = auth()->user()->orders()->with('items')->latest()->get();
    }

    public function getStatusData(string $status): array
    {
        return match (strtolower($status)) {
            'pending'      => ['label' => 'قيد التأكيد', 'color' => 'bg-yellow-100 text-yellow-800 border-yellow-200'],
            'processing'   => ['label' => 'قيد التجهيز', 'color' => 'bg-blue-100 text-blue-800 border-blue-200'],
            'shipped'      => ['label' => 'جاري التوصيل', 'color' => 'bg-indigo-100 text-indigo-800 border-indigo-200'],
            'delivered'    => ['label' => 'تم التسليم', 'color' => 'bg-green-100 text-green-800 border-green-200'],
            'cancelled'    => ['label' => 'ملغي', 'color' => 'bg-red-100 text-red-800 border-red-200'],
            default        => ['label' => 'حالة غير معروفة', 'color' => 'bg-gray-100 text-gray-800 border-gray-200'],
        };
    }
};
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-8 border-b border-gray-200 pb-4">
        <h1 class="text-3xl font-extrabold text-gray-900">📦 سجل طلباتي</h1>
        <p class="text-sm text-gray-500 mt-2">تتبع حالة طلباتك السابقة وتفاصيل الفواتير.</p>
    </div>

    @if($orders->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center border border-gray-200">
            <div class="text-6xl mb-4">🛒</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">لا يوجد لديك أي طلبات سابقة</h2>
            <p class="text-gray-500 mb-6">يبدو أنك لم تقم بأي عملية شراء بعد، تصفح منتجاتنا الآن!</p>
            <a href="{{ route('home') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition shadow-sm">
                العودة للتسوق
            </a>
        </div>
    @else
        <div class="space-y-6">
            @foreach($orders as $order)
                @php $statusData = $this->getStatusData($order->status); @endphp
                <div class="bg-white shadow-sm rounded-xl border border-gray-200 overflow-hidden">
                    
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-center">
                            
                            <div class="text-right">
                                <span class="block text-xs font-medium text-gray-500 uppercase mb-1">رقم الطلب</span>
                                <span class="block font-bold text-gray-900">{{ $order->order_number ?? 'ORD-'.$order->id }}</span>
                            </div>
                            
                            <div class="text-right">
                                <span class="block text-xs font-medium text-gray-500 uppercase mb-1">تاريخ الطلب</span>
                                <span class="block font-medium text-gray-900">{{ $order->created_at->format('Y-m-d') }}</span>
                            </div>
                            
                            <div class="text-right">
                                <span class="block text-xs font-medium text-gray-500 uppercase mb-1">الإجمالي</span>
                                <span class="block font-bold text-blue-600">{{ number_format($order->total_price, 0) }} SYP</span>
                            </div>
                            
                            <div class="text-right md:text-left">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold border {{ $statusData['color'] }}">
                                    {{ $statusData['label'] }}
                                </span>
                            </div>

                        </div>
                    </div>

                    <div class="px-6 py-2 bg-white">
                        <ul class="divide-y divide-gray-100">
                            @foreach($order->items as $item)
                                <li class="py-4 flex justify-between items-center gap-4">
                                    <div class="flex flex-col text-right">
                                        <span class="font-bold text-gray-900 text-base md:text-lg">{{ $item->product_name }}</span>
                                        <span class="text-sm text-gray-500 mt-1">{{ $item->variant_description }}</span>
                                    </div>
                                    <div class="text-left min-w-[100px]">
                                        <p class="text-gray-900 font-bold">{{ number_format($item->unit_price, 0) }} SYP</p>
                                        <p class="text-sm text-gray-500 mt-1">الكمية: <span class="font-bold text-gray-900">{{ $item->quantity }}</span></p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                </div>
            @endforeach
        </div>
    @endif
</div>