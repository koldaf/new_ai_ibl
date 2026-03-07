@extends('layout.app')

{{-- ═══════════════════════════════════════════════════════════
     PAGE META
     Change the title and page-title for each page.
═══════════════════════════════════════════════════════════ --}}
@section('title', 'Page Title')
@section('page-title', 'Page Title')


{{-- ═══════════════════════════════════════════════════════════
     PAGE-SPECIFIC STYLES  (optional)
     Add any CSS that applies only to this page.
     Remove this section if not needed.
═══════════════════════════════════════════════════════════ --}}
@push('styles')
<style>
    /* Page-specific styles here */
</style>
@endpush


{{-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════════════════ --}}
@section('content')

    {{-- ── Page Header ─────────────────────────────────────
         Breadcrumb + optional action button on the right.
         Remove either element if not needed.
    ──────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="font-size:0.8rem;">
                <li class="breadcrumb-item">
                    <a href="{{ auth()->user()->hasRole('admin') ? route('admin.dashboard') : route('student.lessons.index') }}" style="color:var(--bo); text-decoration:none;">
                        <i class="bi bi-grid-1x2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Page Title</li>
            </ol>
        </nav>

        {{-- Primary Action Button --}}
        <a href="#" class="btn btn-sm"
           style="background:var(--bo); color:#fff; border:none; border-radius:8px;
                  font-size:0.85rem; font-weight:500; padding:0.45rem 1rem;">
            <i class="bi bi-plus me-1"></i> Action
        </a>

    </div>


    {{-- ── Flash Messages ───────────────────────────────────
         Displays session success / error alerts automatically.
         Keep this block on every page.
    ──────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2 mb-4"
             role="alert"
             style="border-radius:10px; font-size:0.875rem; border:1px solid rgba(22,163,74,0.2); background:rgba(22,163,74,0.08); color:#16A34A;">
            <i class="bi bi-check-circle-fill"></i>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4"
             role="alert"
             style="border-radius:10px; font-size:0.875rem; border:1px solid rgba(220,38,38,0.2); background:rgba(220,38,38,0.08); color:#DC2626;">
            <i class="bi bi-exclamation-circle-fill"></i>
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert d-flex align-items-start gap-2 mb-4"
             role="alert"
             style="border-radius:10px; font-size:0.875rem; border:1px solid rgba(220,38,38,0.2); background:rgba(220,38,38,0.08); color:#DC2626;">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <ul class="mb-0 ps-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    {{-- ══════════════════════════════════════════════════════
         CONTENT AREA
         Choose and uncomment one of the layout patterns below,
         or build your own using the card component.
    ══════════════════════════════════════════════════════ --}}


    {{-- ┌─────────────────────────────────────────────────┐
         │  PATTERN A — Single full-width card             │
         │  Best for: tables, lists, data grids            │
         └─────────────────────────────────────────────────┘ --}}
    <div class="card">
        <div class="card-body p-0">

            {{-- Card Header --}}
            <div class="d-flex align-items-center justify-content-between px-4 pt-4 pb-3"
                 style="border-bottom:1px solid var(--border);">
                <h2 style="font-family:'DM Serif Display',serif; font-size:1rem; margin:0; letter-spacing:-0.01em;">
                    Section Title
                </h2>
                {{-- Optional: search / filter --}}
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="width:200px;">
                        <span class="input-group-text"
                              style="background:var(--input-bg); border-color:var(--border); color:var(--text-muted);">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control form-control-sm" placeholder="Search…"
                               style="background:var(--input-bg); border-color:var(--border); font-size:0.82rem;">
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Column A</th>
                            <th>Column B</th>
                            <th>Column C</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- @forelse($items as $item) --}}
                        <tr>
                            <td style="color:var(--text-muted)">1</td>
                            <td>Value A</td>
                            <td>Value B</td>
                            <td>Value C</td>
                            <td><span class="badge-status badge-active">Active</span></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="#" class="topbar-btn" title="Edit">
                                        <i class="bi bi-pencil" style="font-size:0.9rem; color:var(--text-muted);"></i>
                                    </a>
                                    <form method="POST" action="#">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="topbar-btn border-0" title="Delete"
                                                onclick="return confirm('Are you sure?')">
                                            <i class="bi bi-trash" style="font-size:0.9rem; color:#DC2626;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        {{-- @empty --}}
                        {{-- <tr><td colspan="6" class="text-center py-4" style="color:var(--text-muted);">No records found.</td></tr> --}}
                        {{-- @endforelse --}}
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            {{-- @if($items->hasPages()) --}}
            <div class="px-4 py-3" style="border-top:1px solid var(--border);">
                {{-- {{ $items->links() }} --}}
            </div>
            {{-- @endif --}}

        </div>
    </div>
    {{-- END PATTERN A --}}


    {{-- ┌─────────────────────────────────────────────────┐
         │  PATTERN B — Two-column layout                  │
         │  Best for: form + summary, split views          │
         └─────────────────────────────────────────────────┘
    <div class="row g-3">

        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body p-4">
                    <h2 style="font-family:'DM Serif Display',serif; font-size:1rem; margin-bottom:1.5rem;">
                        Main Section
                    </h2>
                    {{-- Main content here --}}
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body p-4">
                    <h2 style="font-family:'DM Serif Display',serif; font-size:1rem; margin-bottom:1.5rem;">
                        Side Section
                    </h2>
                    {{-- Sidebar content here --}}
                </div>
            </div>
        </div>

    </div>
    END PATTERN B --}}


    {{-- ┌─────────────────────────────────────────────────┐
         │  PATTERN C — Form card                          │
         │  Best for: create / edit pages                  │
         └─────────────────────────────────────────────────┘
    <div class="card" style="max-width:680px;">
        <div class="card-body p-4">
            <h2 style="font-family:'DM Serif Display',serif; font-size:1rem; margin-bottom:1.5rem;">
                Form Title
            </h2>

            <form method="POST" action="#">
                @csrf
                {{-- @method('PUT') for edit forms --}}

                <div class="mb-3">
                    <label for="field_name" class="form-label"
                           style="font-size:0.78rem; font-weight:500; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted);">
                        Field Label
                    </label>
                    <input type="text"
                           id="field_name"
                           name="field_name"
                           class="form-control @error('field_name') is-invalid @enderror"
                           value="{{ old('field_name') }}"
                           placeholder="Placeholder text" />
                    @error('field_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="field_select" class="form-label"
                           style="font-size:0.78rem; font-weight:500; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted);">
                        Select Field
                    </label>
                    <select id="field_select" name="field_select"
                            class="form-select @error('field_select') is-invalid @enderror">
                        <option value="">— Choose —</option>
                        <option value="option_1" {{ old('field_select') == 'option_1' ? 'selected' : '' }}>Option 1</option>
                        <option value="option_2" {{ old('field_select') == 'option_2' ? 'selected' : '' }}>Option 2</option>
                    </select>
                    @error('field_select')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="field_textarea" class="form-label"
                           style="font-size:0.78rem; font-weight:500; letter-spacing:0.04em; text-transform:uppercase; color:var(--text-muted);">
                        Textarea
                    </label>
                    <textarea id="field_textarea" name="field_textarea" rows="4"
                              class="form-control @error('field_textarea') is-invalid @enderror"
                              placeholder="Enter description…">{{ old('field_textarea') }}</textarea>
                    @error('field_textarea')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn"
                            style="background:var(--bo); color:#fff; border:none; border-radius:8px;
                                   font-size:0.875rem; font-weight:500; padding:0.55rem 1.4rem;">
                        Save
                    </button>
                    <a href="#" class="btn btn-sm"
                       style="border:1px solid var(--border); color:var(--text-muted); border-radius:8px;
                              font-size:0.875rem; padding:0.55rem 1.1rem; text-decoration:none;">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
    END PATTERN C --}}


@endsection


{{-- ═══════════════════════════════════════════════════════════
     PAGE-SPECIFIC SCRIPTS  (optional)
     Add any JS that applies only to this page.
     Remove this section if not needed.
═══════════════════════════════════════════════════════════ --}}
@push('scripts')
<script>
    // Page-specific JavaScript here
</script>
@endpush