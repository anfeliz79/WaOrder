<!DOCTYPE html>
<html><head><title>Procesando...</title></head>
<body>
<script>
    window.opener && window.opener.postMessage({
        type: 'paypal-complete',
        success: @json($success),
        message: @json($message ?? ''),
        redirect: @json($redirect ?? null),
    }, '*');
    window.close();
</script>
<p style="text-align:center;margin-top:40px;font-family:sans-serif;color:#6b7280">
    {{ $success ? 'Listo! Puedes cerrar esta ventana.' : ($message ?? 'Hubo un error. Cierra esta ventana e intenta de nuevo.') }}
</p>
</body></html>
