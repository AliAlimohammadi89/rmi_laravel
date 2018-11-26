<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ ( isset($page_title) )?$page_title:'RMI Shopfiy App' }}</title>
    {{--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/picnic/6.1.1/picnic.min.css">--}}
    {{--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/picnic/6.1.1/plugins.min.css">--}}
    <link rel="stylesheet" href="{{ url('assets/bootstrap-4.0.0-beta-dist/css/bootstrap.css') }}">
    <script src="{{ url('js/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ url('assets/tether-1.3.3/dist/js/tether.min.js') }}"></script>
    <script src="{{ url('assets/popper.js-1.12.3/dist/umd/popper.min.js') }}"></script>
    <script src="{{ url('assets/bootstrap-4.0.0-beta-dist/js/bootstrap.min.js') }}"></script>
    <link rel="stylesheet" href="{{ url('css/style.css') }}">
    <base href="{{ url('/') }}">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light  navbar-dark bg-dark">
    <a class="navbar-brand" href="{{ url('/') }}">RMI Shopify</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavDropdown">
        @if( session('client_id') )
            <ul class="navbar-nav">
                <li class="nav-item active dropdown">
                    <a class="nav-link dropdown-toggle" href="http://example.com" id="navbarDropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ \App\Client::find(session('client_id'))->shopify_store  }}
                    </a>
                    <div class="dropdown-menu"   aria-labelledby="navbarDropdownMenuLink">
                        <a class="dropdown-item" href="{{ url('history') }}">History</a>
                        <a class="dropdown-item" href="{{ url('logout') }}">Logout</a>
                    </div>
                </li>
            </ul>

        @else
            <span class="navbar-text d-none">
            You Don't Login Yet
            </span>
        @endif
    </div>
</nav>





<main>
    @yield('content')
</main>
 </body>
</html>