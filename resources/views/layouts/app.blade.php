<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentinelPay Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-blue-900 text-white p-4 shadow mb-6">
        <div class="container mx-auto flex justify-between">
            <a href="{{ route('dashboard.index') }}" class="font-bold text-xl tracking-wider">SentinelPay Admin</a>
        </div>
    </nav>
    <main class="container mx-auto px-4">
        @yield('content')
    </main>
</body>
</html>
