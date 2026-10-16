<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'سوق سوريا' }}</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-900 flex flex-col min-h-screen">

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="text-2xl font-extrabold text-blue-600 hover:text-blue-700 transition">
                🛍️ سوق سوريا
            </a>
            
            {{-- <div class="flex items-center">
                <livewire:storefront.cart-header-widget />
            </div> --}}
            <div class="flex items-center gap-6">
                @auth
                    <a href="{{ route('my-orders') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition">
                        📦 طلباتي
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-blue-600 transition">
                        تسجيل الدخول
                    </a>
                @endauth

                <livewire:storefront.cart-header-widget />
                
            </div>
        </div>
    </header>

    <main class="flex-grow">
        {{ $slot }}
    </main>

    <footer class="bg-gray-800 text-white text-center py-6 mt-12">
        <p class="text-sm">جميع الحقوق محفوظة &copy; 2026 سوق سوريا</p>
    </footer>
    <livewire:storefront.cart-drawer />
</body>
</html>