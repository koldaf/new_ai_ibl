<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="auto">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — AI-IBL</title>

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet"/>
    <link href="{{ asset('summernote/summernote-bs5.min.css') }}" rel="stylesheet">
    {{-- Vite compiled CSS --}}
    @vite('resources/css/app.css')

    <style>
        /* ── Design tokens ─────────────────────────────── */
        :root {
            --bo:         #CC5500;
            --bo-dark:    #A84400;
            --bo-light:   #E8722E;
            --bo-muted:   rgba(204,85,0,0.09);
            --sidebar-w:  260px;
            --topbar-h:   60px;
            --radius:     10px;
            --transition: 0.22s ease;
            --primary: #1A2A40; /* warm navy */ 
            --primary-dark: #111C2B; 
            --primary-light: #2F4466; 

            /* override Bootstrap primary */
            --bs-primary: var(--bo); 
            --bs-primary-rgb: 204, 85, 0; /* override button-specific colours */ 
            --bs-btn-bg: var(--bo); 
            --bs-btn-border-color: var(--bo); 
            --bs-btn-hover-bg: var(--bo-dark); 
            --bs-btn-hover-border-color: var(--bo-dark);
        }

        [data-bs-theme="light"], html {
            --bg-body:    #F5F2EE;
            --bg-sidebar: #FFFFFF;
            --bg-card:    #FFFFFF;
            --bg-topbar:  #FFFFFF;
            --border:     #E6E1D9;
            --text-base:  #1A1714;
            --text-muted: #8A8480;
            --input-bg:   #FAFAF8;
            --shadow:     0 2px 24px rgba(0,0,0,0.06);
        }

        [data-bs-theme="dark"] {
            --bg-body:    #0F0E0D;
            --bg-sidebar: #181614;
            --bg-card:    #1C1A18;
            --bg-topbar:  #181614;
            --border:     #2C2926;
            --text-base:  #F0ECE6;
            --text-muted: #625D58;
            --input-bg:   #141210;
            --shadow:     0 2px 24px rgba(0,0,0,0.3);
        }

        /* ── Reset & base ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-base);
            margin: 0;
            min-height: 100vh;
        }

        /* ── Top accent bar ─────────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--bo), var(--bo-light));
            z-index: 1100;
        }

        /* ════════════════════════════════════════════════
           SIDEBAR
        ════════════════════════════════════════════════ */
        .sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 1050;
            transition: transform var(--transition);
            padding-top: 3px; /* offset accent bar */
        }

        /* Brand */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1.35rem 1.5rem 1.2rem;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
        }
        .brand-icon {
            width: 36px; height: 36px;
            background: var(--bo);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .brand-icon i { color: #fff; font-size: 1rem; }
        .brand-text {
            font-family: 'DM Serif Display', serif;
            font-size: 1.3rem;
            color: var(--bo);
            letter-spacing: -0.02em;
            line-height: 1;
        }

        /* Nav */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem 0.75rem;
        }
        .nav-label {
            font-size: 0.68rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0.25rem 0.75rem 0.5rem;
            margin-top: 0.5rem;
        }

        .nav-item-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.85rem;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 400;
            transition: background var(--transition), color var(--transition);
            position: relative;
        }
        .nav-item-link i { font-size: 1rem; flex-shrink: 0; }
        .nav-item-link:hover {
            background: var(--bo-muted);
            color: var(--bo);
        }
        .nav-item-link.active {
            background: var(--bo-muted);
            color: var(--bo);
            font-weight: 500;
        }
        .nav-item-link.active::before {
            content: '';
            position: absolute;
            left: 0; top: 20%; bottom: 20%;
            width: 3px;
            background: var(--bo);
            border-radius: 0 3px 3px 0;
            margin-left: -0.75rem;
        }

        /* Submenu accordion */
        .nav-submenu { padding-left: 1.6rem; }
        .nav-submenu .nav-item-link {
            font-size: 0.83rem;
            padding: 0.5rem 0.85rem;
        }
        .nav-submenu .nav-item-link::before { margin-left: -2.35rem; }

        /* Collapse arrow */
        .collapse-arrow {
            margin-left: auto;
            font-size: 0.7rem;
            transition: transform var(--transition);
        }
        [aria-expanded="true"] .collapse-arrow { transform: rotate(180deg); }

        /* Sidebar footer */
        .sidebar-footer {
            border-top: 1px solid var(--border);
            padding: 1rem 0.75rem;
        }
        .sidebar-footer .nav-item-link {
            color: #C0392B;
        }
        .sidebar-footer .nav-item-link:hover {
            background: rgba(192,57,43,0.08);
            color: #C0392B;
        }

        /* ════════════════════════════════════════════════
           TOPBAR
        ════════════════════════════════════════════════ */
        .topbar {
            position: fixed;
            top: 3px; /* below accent bar */
            left: var(--sidebar-w);
            right: 0;
            height: var(--topbar-h);
            background: var(--bg-topbar);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            z-index: 1040;
        }

        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-right { display: flex; align-items: center; gap: 0.5rem; }

        .page-title {
            font-family: 'DM Serif Display', serif;
            font-size: 1.15rem;
            color: var(--text-base);
            letter-spacing: -0.01em;
            margin: 0;
        }

        /* Toggle sidebar button */
        .btn-sidebar-toggle {
            background: none; border: none;
            color: var(--text-muted);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.3rem;
            border-radius: 6px;
            transition: color var(--transition), background var(--transition);
            display: none; /* visible on mobile */
        }
        .btn-sidebar-toggle:hover { color: var(--bo); background: var(--bo-muted); }

        /* Topbar icon buttons */
        .topbar-btn {
            background: none; border: none;
            color: var(--text-muted);
            font-size: 1.05rem;
            cursor: pointer;
            width: 36px; height: 36px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            transition: color var(--transition), background var(--transition);
            position: relative;
        }
        .topbar-btn:hover { color: var(--bo); background: var(--bo-muted); }

        .notif-badge {
            position: absolute; top: 5px; right: 5px;
            width: 7px; height: 7px;
            background: var(--bo);
            border-radius: 50%;
        }

        /* Avatar */
        .avatar {
            width: 34px; height: 34px;
            background: var(--bo);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color var(--transition);
        }
        .avatar:hover { border-color: var(--bo-light); }

        /* Dropdown */
        .topbar-dropdown .dropdown-menu {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            background: var(--bg-card);
            min-width: 180px;
            padding: 0.4rem;
        }
        .topbar-dropdown .dropdown-item {
            font-size: 0.85rem;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            color: var(--text-base);
            display: flex; align-items: center; gap: 0.5rem;
        }
        .topbar-dropdown .dropdown-item:hover {
            background: var(--bo-muted);
            color: var(--bo);
        }
        .topbar-dropdown .dropdown-divider { border-color: var(--border); }

        /* ════════════════════════════════════════════════
           MAIN CONTENT
        ════════════════════════════════════════════════ */
        .main-content {
            margin-left: var(--sidebar-w);
            padding-top: calc(var(--topbar-h) + 3px);
            min-height: 100vh;
        }
        .content-inner {
            padding: 1.75rem 2rem;
            animation: fadeIn 0.4s ease both;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Cards ── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }
        .card-header-custom {
            padding: 1.1rem 1.4rem 0;
            font-family: 'DM Serif Display', serif;
            font-size: 1rem;
            letter-spacing: -0.01em;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.3rem 1.4rem;
            display: flex; align-items: flex-start; gap: 1rem;
            box-shadow: var(--shadow);
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .stat-icon.orange { background: var(--bo-muted); color: var(--bo); }
        .stat-icon.green  { background: rgba(22,163,74,0.1); color: #16A34A; }
        .stat-icon.blue   { background: rgba(37,99,235,0.1); color: #2563EB; }
        .stat-icon.purple { background: rgba(124,58,237,0.1); color: #7C3AED; }

        .stat-value {
            font-family: 'DM Serif Display', serif;
            font-size: 1.8rem;
            line-height: 1;
            color: var(--text-base);
            margin: 0.2rem 0 0.15rem;
        }
        .stat-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-delta {
            font-size: 0.75rem;
            font-weight: 500;
        }
        .stat-delta.up   { color: #16A34A; }
        .stat-delta.down { color: #DC2626; }

        /* ── Tables ── */
        .table {
            font-size: 0.875rem;
            color: var(--text-base);
        }
        .table thead th {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            padding: 0.6rem 1rem;
            font-weight: 500;
            background: transparent;
        }
        .table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .table tbody tr:last-child td { border-bottom: none; }
        .table tbody tr:hover td { background: var(--bo-muted); }

        /* ── Badges ── */
        .badge-status {
            font-size: 0.7rem;
            font-weight: 500;
            padding: 0.25em 0.65em;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }
        .badge-active   { background: rgba(22,163,74,0.12); color: #16A34A; }
        .badge-inactive { background: rgba(220,38,38,0.10); color: #DC2626; }
        .badge-pending  { background: rgba(204,85,0,0.10);  color: var(--bo); }

        /* ── Sidebar overlay (mobile) ── */
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1045;
        }
        /* _____Summernote custom overrides_____ */
        /*.note-modal { z-index: 2000 !important; } 
        .note-modal-backdrop { z-index: 1990 !important; }
        .note-modal-backdrop { display: none !important; }*/

        /* ════════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════════ */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 4px 0 30px rgba(0,0,0,0.15);
            }
            .sidebar-overlay.open { display: block; }
            .main-content { margin-left: 0; }
            .topbar { left: 0; }
            .btn-sidebar-toggle { display: flex; align-items: center; justify-content: center; }
            .content-inner { padding: 1.25rem 1rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

    @php
        $dashboardRoute = route('student.lessons.index');
        $dashboardRouteName = 'student.lessons.index';

        if (auth()->check() && auth()->user()->hasRole('admin')) {
            $dashboardRoute = route('admin.dashboard');
            $dashboardRouteName = 'admin.dashboard';
        } elseif (auth()->check() && auth()->user()->hasRole('teacher')) {
            $dashboardRoute = route('teacher.dashboard');
            $dashboardRouteName = 'teacher.dashboard';
        }
    @endphp

    {{-- ═══ SIDEBAR ═══════════════════════════════════════ --}}
    <aside class="sidebar" id="sidebar">

        <a href="{{ $dashboardRoute }}" class="sidebar-brand">
            <div class="brand-icon"><i class="bi bi-cpu"></i></div>
            <span class="brand-text">AI-IBL</span>
        </a>

        <nav class="sidebar-nav">

            {{-- Main --}}
            <div class="nav-label">Main</div>

                <a href="{{ $dashboardRoute }}"
                    class="nav-item-link {{ request()->routeIs($dashboardRouteName) ? 'active' : '' }}">
                <i class="bi bi-grid-1x2"></i>
                <span>Dashboard</span>
            </a>

            @if(auth()->user()->role === 'admin')
            {{-- Users --}}
            <div class="nav-label mt-2">Admin Role</div>

            <a href="{{ route('admin.users.index') }}"
               class="nav-item-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>

            {{-- Lesson Setup --}}
            <a class="nav-item-link {{ request()->routeIs('admin.lessons.*') ? 'active' : '' }}"
               data-bs-toggle="collapse"
               href="#lessonSubmenu"
               role="button"
               aria-expanded="{{ request()->routeIs('admin.lessons.*') ? 'true' : 'false' }}"
               aria-controls="lessonSubmenu">
                <i class="bi bi-book"></i>
                <span>Lesson Setup</span>
                <i class="bi bi-chevron-down collapse-arrow"></i>
            </a>

            <div class="collapse {{ request()->routeIs('admin.lessons.*') ? 'show' : '' }}" id="lessonSubmenu">
                <div class="nav-submenu">
                    <a href="{{ route('admin.lessons.index') }}"
                       class="nav-item-link {{ request()->routeIs('admin.lessons.index') ? 'active' : '' }}">
                        <i class="bi bi-list-ul"></i>
                        <span>Lesson List</span>
                    </a>
                    <a href="{{ route('admin.lessons.create') }}"
                       class="nav-item-link {{ request()->routeIs('admin.lessons.create') ? 'active' : '' }}">
                        <i class="bi bi-plus-circle"></i>
                        <span>Add Lesson</span>
                    </a>
                </div>
            </div>
            @endif
            @if(auth()->user()->role === 'student')
                {{-- My Lessons --}}
                <div class="nav-label mt-2">My Learning</div>
                <a href="{{ route('student.lessons.index') }}"
                       class="nav-item-link {{ request()->routeIs('student.lessons.index') ? 'active' : '' }}">
                        <i class="bi bi-list-ul"></i>
                        <span>Lesson List</span>
                </a>
            @endif
            @if(auth()->user()->role === 'teacher')
                <div class="nav-label mt-2">Teaching</div>
                <a href="{{ route('teacher.dashboard') }}"
                   class="nav-item-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-graph-up"></i>
                    <span>Progress Dashboard</span>
                </a>
            @endif
        </nav>

        {{-- Logout --}}
        <div class="sidebar-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-item-link w-100 border-0 bg-transparent text-start">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>

    </aside>

    {{-- Mobile sidebar overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- ═══ TOPBAR ══════════════════════════════════════════ --}}
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
        </div>

        <div class="topbar-right">

            {{-- Notifications --}}
            <button class="topbar-btn" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-badge"></span>
            </button>

            {{-- User dropdown --}}
            <div class="dropdown topbar-dropdown">
                <div class="avatar" data-bs-toggle="dropdown" aria-expanded="false" role="button" tabindex="0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}{{ strtoupper(substr(explode(' ', auth()->user()->name ?? 'U ')[1] ?? '', 0, 1)) }}
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <span class="dropdown-item-text px-3 py-2">
                            <div style="font-size:0.85rem; font-weight:500; color:var(--text-base)">{{ auth()->user()->name ?? 'User' }}</div>
                            <div style="font-size:0.75rem; color:var(--text-muted)">{{ auth()->user()->email ?? '' }}</div>
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item" style="color:#C0392B;">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

        </div>
    </header>

    {{-- ═══ MAIN CONTENT ════════════════════════════════════ --}}
    <main class="main-content">
        <div class="content-inner">
            @yield('content')
        </div>
    </main>

    {{-- Bootstrap JS --}}

    {{-- Vite JS --}}
    <script src="{{ asset('js/jquery-4.0.0.min.js') }}"></script>
    @vite('resources/js/app.js')
    <script src="{{ asset('summernote/summernote-bs5.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            $('.wysiwyg-editor').summernote({
                placeholder: 'Input your content here...',
                tabsize: 2,
                height: 200
            });
        });
        // ── Mobile sidebar toggle ───────────────────────
        const sidebar        = document.getElementById('sidebar');
        const overlay        = document.getElementById('sidebarOverlay');
        const sidebarToggle  = document.getElementById('sidebarToggle');

        function openSidebar()  { sidebar.classList.add('open');  overlay.classList.add('open'); }
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }

        sidebarToggle?.addEventListener('click', () =>
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar()
        );
        overlay?.addEventListener('click', closeSidebar);

        // ── Bootstrap auto theme via media query ───────
        // Bootstrap 5.3 handles data-bs-theme="auto" natively — no extra JS needed.
    </script>

    @stack('scripts')
</body>
</html>