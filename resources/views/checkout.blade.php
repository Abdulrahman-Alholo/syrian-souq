<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إتمام الدفع</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { font-family: Arial; padding: 50px; background: #f9f9f9; }
        .checkout-box { max-width: 400px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        #card-element { padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px; }
        button { width: 100%; padding: 15px; background: #6366f1; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        button:hover { background: #4f46e5; }
        #payment-message { margin-top: 15px; color: red; text-align: center; }
    </style>
</head>
<body>

<div class="checkout-box">
    <h2 style="text-align: center;">ادفع الآن بأمان</h2>
    <form id="payment-form">
        <div id="card-element"></div>
        <button id="submit">دفع الآن</button>
        <div id="payment-message"></div>
    </form>
</div>

<script>
    // 1. ضع مفتاحك العام هنا (Publishable Key)
    const stripe = Stripe('{{ env("STRIPE_KEY") }}');
    const elements = stripe.elements();
    const cardElement = elements.create('card');
    cardElement.mount('#card-element');

    const form = document.getElementById('payment-form');
    const messageDiv = document.getElementById('payment-message');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        document.getElementById('submit').disabled = true;
        messageDiv.innerText = "جاري معالجة الدفع...";

        try {
            // 2. جلب الـ Client Secret من السيرفر الخاص بك
            // (يجب أن يكون لديك سلة فيها منتجات لكي تنجح هذه الخطوة)
            const response = await fetch('/api/checkout/pay', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            const data = await response.json();
            
            if (!data.client_secret) {
                throw new Error(data.error || "لم يتم إرجاع Client Secret من السيرفر.");
            }

            // 3. تأكيد الدفع عبر Stripe
            const { error, paymentIntent } = await stripe.confirmCardPayment(data.client_secret, {
                payment_method: { card: cardElement }
            });

            if (error) {
                messageDiv.innerText = error.message;
                document.getElementById('submit').disabled = false;
            } else if (paymentIntent.status === 'succeeded') {
                messageDiv.style.color = "green";
                messageDiv.innerText = "تم الدفع بنجاح! 🎉 جاري تحويلك...";
                // 🟢 توجيه الزبون لصفحة النجاح مع تمرير رقم الطلب الآمن (Payment Intent ID)
                window.location.href = `/checkout/success?payment_intent=${paymentIntent.id}`;
                // يمكنك توجيهه لصفحة النجاح هنا
            }
        } catch (err) {
            messageDiv.innerText = "حدث خطأ: " + err.message;
            document.getElementById('submit').disabled = false;
        }
    });
</script>

</body>
</html>