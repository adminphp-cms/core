<!DOCTYPE html>
<html lang="{{ $config['defaultLocale'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AdminPHP</title>
    {{-- Applies the theme chosen in "Mon compte" before the first paint. --}}
    <script>try { const theme = localStorage.getItem('adminphp.theme'); if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme } catch (error) {}</script>
    {{ app(\AdminPhp\AdminPhp::class)->assets() }}
</head>
<body>
    <div id="adminphp"></div>
    <script>window.AdminPhp = @json($config);</script>
</body>
</html>
