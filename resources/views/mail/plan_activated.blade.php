<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan activado</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #0052FF, #00D1FF); padding: 32px 40px; text-align: center; }
        .header h1 { margin: 0; color: #fff; font-size: 22px; font-weight: 700; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,.8); font-size: 14px; }
        .body { padding: 32px 40px; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; border-radius: 20px; padding: 4px 14px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        h2 { font-size: 18px; color: #111827; margin: 0 0 8px; }
        p { color: #6b7280; line-height: 1.65; font-size: 14px; margin: 0 0 16px; }
        .detail-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
        .detail-row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; }
        .detail-value { color: #111827; font-weight: 500; }
        .btn { display: block; text-align: center; background: #0052FF; color: #fff; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-size: 14px; font-weight: 600; margin: 24px 0 0; }
        .footer { background: #f9fafb; padding: 20px 40px; text-align: center; }
        .footer p { font-size: 12px; color: #9ca3af; margin: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>WaOrder</h1>
        <p>Plataforma de pedidos via WhatsApp</p>
    </div>
    <div class="body">
        <span class="badge">Plan activo</span>
        <h2>Tu plan fue activado!</h2>
        <p>
            Hola {{ $user->name }}, tu suscripcion a WaOrder ya esta <strong>activa</strong>.
            Ya puedes configurar tu restaurante y empezar a recibir pedidos.
        </p>

        @php
            $plan = $subscription->plan;
            $billingLabel = $subscription->billing_period === 'annual' ? 'Anual' : 'Mensual';
            $periodLabel = $subscription->billing_period === 'annual' ? '/anual' : '/mes';
            $currency = $plan->currency ?? 'DOP';
        @endphp

        <div class="detail-box">
            <div class="detail-row">
                <span class="detail-label">Plan</span>
                <span class="detail-value">{{ $plan->name ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Modalidad</span>
                <span class="detail-value">{{ $billingLabel }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Precio</span>
                <span class="detail-value">{{ number_format($subscription->price, 2) }} {{ $currency }}{{ $periodLabel }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Metodo de pago</span>
                <span class="detail-value">{{ ucfirst(str_replace('_', ' ', $subscription->payment_method ?? 'N/A')) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Proximo cobro</span>
                <span class="detail-value">{{ $subscription->current_period_end?->format('d/m/Y') ?? 'N/A' }}</span>
            </div>
        </div>

        <a href="{{ config('app.url') }}/setup" class="btn">Configurar mi restaurante</a>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} WaOrder &middot; Este correo fue generado automaticamente.</p>
    </div>
</div>
</body>
</html>
