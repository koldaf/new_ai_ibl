@extends('layout.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

    {{-- ── Stat Cards ──────────────────────────────────── --}}
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value">1,284</div>
                    <div class="stat-delta up"><i class="bi bi-arrow-up-short"></i>12% this month</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-book-fill"></i></div>
                <div>
                    <div class="stat-label">Active Lessons</div>
                    <div class="stat-value">47</div>
                    <div class="stat-delta up"><i class="bi bi-arrow-up-short"></i>5 added</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-mortarboard-fill"></i></div>
                <div>
                    <div class="stat-label">Enrolled Students</div>
                    <div class="stat-value">863</div>
                    <div class="stat-delta up"><i class="bi bi-arrow-up-short"></i>8% this week</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="stat-label">Completion Rate</div>
                    <div class="stat-value">74%</div>
                    <div class="stat-delta down"><i class="bi bi-arrow-down-short"></i>2% vs last month</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Recent Users + Recent Lessons ──────────────── --}}
    <div class="row g-3">

        {{-- Recent Users --}}
        <div class="col-12 col-lg-7">
            <div class="card h-100">
                <div class="card-body p-0">
                    <div class="d-flex align-items-center justify-content-between px-4 pt-4 pb-3">
                        <h2 class="card-header-custom p-0 m-0">Recent Users</h2>
                        <a href="#" class="btn btn-sm"
                           style="font-size:0.78rem; color:var(--bo); text-decoration:none; border:1px solid var(--border); border-radius:7px; padding:0.3rem 0.75rem;">
                            View all <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Replace with: @foreach($recentUsers as $user) --}}
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:50%;background:var(--bo-muted);color:var(--bo);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:500;flex-shrink:0;">JD</div>
                                            <span>John Doe</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted)">john@example.com</td>
                                    <td>Admin</td>
                                    <td><span class="badge-status badge-active">Active</span></td>
                                    <td style="color:var(--text-muted)">Jan 15, 2025</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:50%;background:rgba(37,99,235,0.1);color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:500;flex-shrink:0;">SN</div>
                                            <span>Sarah Nkosi</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted)">sarah.n@example.com</td>
                                    <td>Student</td>
                                    <td><span class="badge-status badge-active">Active</span></td>
                                    <td style="color:var(--text-muted)">Feb 2, 2025</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:50%;background:rgba(124,58,237,0.1);color:#7C3AED;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:500;flex-shrink:0;">BM</div>
                                            <span>Bongani Moyo</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted)">b.moyo@example.com</td>
                                    <td>Instructor</td>
                                    <td><span class="badge-status badge-pending">Pending</span></td>
                                    <td style="color:var(--text-muted)">Feb 20, 2025</td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width:30px;height:30px;border-radius:50%;background:rgba(220,38,38,0.1);color:#DC2626;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:500;flex-shrink:0;">LV</div>
                                            <span>Lerato Vilakazi</span>
                                        </div>
                                    </td>
                                    <td style="color:var(--text-muted)">l.vilakazi@example.com</td>
                                    <td>Student</td>
                                    <td><span class="badge-status badge-inactive">Inactive</span></td>
                                    <td style="color:var(--text-muted)">Mar 1, 2025</td>
                                </tr>
                                {{-- @endforeach --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Lessons --}}
        <div class="col-12 col-lg-5">
            <div class="card h-100">
                <div class="card-body p-0">
                    <div class="d-flex align-items-center justify-content-between px-4 pt-4 pb-3">
                        <h2 class="card-header-custom p-0 m-0">Recent Lessons</h2>
                        <a href="#" class="btn btn-sm"
                           style="font-size:0.78rem; color:#fff; background:var(--bo); border:none; border-radius:7px; padding:0.3rem 0.75rem;">
                            <i class="bi bi-plus"></i> Add
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Lesson</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Replace with: @foreach($recentLessons as $lesson) --}}
                                <tr>
                                    <td>
                                        <div style="font-weight:500; font-size:0.85rem;">Intro to AI</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted)">14 students</div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:0.82rem;">Foundations</td>
                                    <td><span class="badge-status badge-active">Active</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:500; font-size:0.85rem;">Machine Learning 101</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted)">9 students</div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:0.82rem;">ML Track</td>
                                    <td><span class="badge-status badge-active">Active</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:500; font-size:0.85rem;">NLP Deep Dive</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted)">6 students</div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:0.82rem;">Advanced</td>
                                    <td><span class="badge-status badge-pending">Draft</span></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="font-weight:500; font-size:0.85rem;">Ethics in AI</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted)">22 students</div>
                                    </td>
                                    <td style="color:var(--text-muted); font-size:0.82rem;">Foundations</td>
                                    <td><span class="badge-status badge-inactive">Inactive</span></td>
                                </tr>
                                {{-- @endforeach --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

@endsection