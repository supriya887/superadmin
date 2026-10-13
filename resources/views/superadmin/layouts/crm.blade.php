<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tizzy Dashboard</title>

    <link rel="stylesheet" href="{{ asset('superadmin/crm/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('superadmin/crm/css/crm.css') }}">
    <link rel="stylesheet" href="{{ asset('superadmin/crm/css/responsive.css') }}">

    <!-- Remix / Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>

    <!--========================
        Header
    =========================-->

    @include('superadmin.crm.partials.header')
    <div class="dashboard">
        @include('superadmin.crm.partials.sidebar')
           <main class="content">

        @yield('content')
        </main>
        <div class="copy-right">
            <p>© 2026 Tizzy Group. All rights reserved</p>
        </div>

    </div>
    @include('superadmin.crm.partials.footer')
