<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Personaliza tu pedido</title>
    @vite(['resources/js/menu-app.js'])
</head>
<body>
    <div id="menu-app" data-token="{{ $token }}" data-page="item"></div>
</body>
</html>
