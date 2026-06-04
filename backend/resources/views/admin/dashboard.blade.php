<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BlendBeats Admin</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/css/adminlte.min.css">
        <style>
            :root {
                color-scheme: dark;
            }

            body {
                background: #0b1120;
            }

            .content-wrapper {
                min-height: 100vh;
                background: #0b1120;
            }

            .card {
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 8px;
                background: #111827;
            }
        </style>
    </head>
    <body class="layout-fixed dark-mode">
        <div class="wrapper">
            <nav class="main-header navbar navbar-expand navbar-dark">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">BlendBeats Admin</a>
                    </li>
                </ul>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <form action="{{ route('admin.logout') }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-light">Sign Out</button>
                        </form>
                    </li>
                </ul>
            </nav>

            <aside class="main-sidebar sidebar-dark-primary elevation-4">
                <a href="{{ route('admin.dashboard') }}" class="brand-link">
                    <span class="brand-text font-weight-bold">BlendBeats</span>
                </a>
                <div class="sidebar">
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
                            <li class="nav-item">
                                <a href="{{ route('admin.dashboard') }}" class="nav-link active">
                                    <p>Dashboard</p>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </aside>

            <main class="content-wrapper">
                <div class="content-header">
                    <div class="container-fluid">
                        <h1 class="m-0">Dashboard</h1>
                    </div>
                </div>

                <section class="content">
                    <div class="container-fluid">
                        <div class="card">
                            <div class="card-body">
                                <h2 class="h4">BlendBeats Admin</h2>
                                <p class="mb-0">Laravel is managing the backend database, API routes, users, and separate admin table.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta3/dist/js/adminlte.min.js"></script>
    </body>
</html>
