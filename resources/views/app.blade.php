<!DOCTYPE html>
<html lang="{{ $config['defaultLocale'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AdminPHP</title>
    {{ app(\Gmarineau\AdminPhp\AdminPhp::class)->assets() }}
</head>
<body>
    <div id="adminphp"></div>
    <script>window.AdminPhp = @json($config);</script>
</body>
</html>
