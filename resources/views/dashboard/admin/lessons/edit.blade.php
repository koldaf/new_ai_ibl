@extends('layout.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1>Edit Lesson: {{ $lesson->title }}</h1>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <!-- Basic Info Update Form -->
            <div class="card mb-4">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <form action="{{ route('admin.lessons.update', $lesson) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="{{ $lesson->title }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="{{ $lesson->subject }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="grade_level" class="form-label">Grade Level</label>
                                <input type="text" class="form-control" id="grade_level" name="grade_level" value="{{ $lesson->grade_level }}">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control wysiwyg-editor" id="description" name="description" rows="2">{{ $lesson->description }}</textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Basic Info</button>
                    </form>
                </div>
            </div>

            <!-- 5E Tabs -->
            <ul class="nav nav-tabs" id="lessonTabs" role="tablist">
                @foreach($stages as $index => $stage)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index == 0 ? 'active' : '' }}" id="{{ $stage }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $stage }}" type="button" role="tab" aria-controls="{{ $stage }}" aria-selected="{{ $index == 0 ? 'true' : 'false' }}">{{ ucfirst($stage) }}</button>
                </li>
                @endforeach
            </ul>

            <div class="tab-content p-3 border border-top-0 bg-white" id="lessonTabsContent">
                @foreach($stages as $index => $stage)
                <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="{{ $stage }}" role="tabpanel" aria-labelledby="{{ $stage }}-tab">
                    
                    <!-- Text / WYSIWYG Form -->
                    <div class="card mb-3">
                        <div class="card-header">{{ ucfirst($stage) }} Content</div>
                        <div class="card-body">
                            <form class="stage-text-form" data-stage="{{ $stage }}">
                                @csrf
                                <input type="hidden" name="content_type" value="{{ in_array($stage, ['explain', 'elaborate']) ? 'wysiwyg' : 'text' }}">
                                <div class="mb-3">
                                    @if(in_array($stage, ['explain', 'elaborate']))
                                        <textarea class="form-control wysiwyg-editor" name="content" rows="10">{{ $stageData[$stage]['content']->content ?? '' }}</textarea>
                                    @else
                                        <textarea class="form-control wysiwyg-editor" name="content" rows="5">{{ $stageData[$stage]['content']->content ?? '' }}</textarea>
                                    @endif
                                </div>
                                <button type="submit" class="btn btn-primary">Save {{ ucfirst($stage) }} Text</button>
                            </form>
                        </div>
                    </div>

                    <!-- Media Upload Section -->
                    <div class="card">
                        <div class="card-header">Media Files (Optional)</div>
                        <div class="card-body">
                            <!-- Upload Form -->
                            <form class="media-upload-form" data-stage="{{ $stage }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="media_type_{{ $stage }}" class="form-label">Media Type</label>
                                        <select name="media_type" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="video">Video</option>
                                            <option value="image">Image</option>
                                            <option value="pdf">PDF</option>
                                            @if($stage == 'explore')
                                                <option value="phet_html">PhET Simulation (HTML)</option>
                                            @endif
                                            @if($stage == 'evaluate')
                                                <option value="csv">CSV (Questions)</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label for="file_{{ $stage }}" class="form-label">File</label>
                                        <input type="file" name="file" class="form-control" required>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="title_{{ $stage }}" class="form-label">Title (optional)</label>
                                        <input type="text" name="title" class="form-control">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-success d-block">Upload</button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="description_{{ $stage }}" class="form-label">Description (optional)</label>
                                        <textarea name="description" class="form-control wysiwyg-editor" rows="2"></textarea>
                                    </div>
                                </div>
                            </form>

                            <!-- Media List -->
                            <div class="media-list mt-4" data-stage="{{ $stage }}">
                                @forelse($stageData[$stage]['media'] as $media)
                                <div class="media-item d-flex align-items-center justify-content-between p-2 border mb-2" data-id="{{ $media->id }}">
                                    <div>
                                        <strong>{{ $media->file_name }}</strong> 
                                        <span class="badge bg-info">{{ $media->media_type }}</span>
                                        @if($media->title) <br><small>Title: {{ $media->title }}</small> @endif
                                    </div>
                                    <div>
                                        <a href="{{ $media->url }}" target="_blank" class="btn btn-sm btn-secondary">View</a>
                                        <button class="btn btn-sm btn-danger delete-media" data-id="{{ $media->id }}">Delete</button>
                                    </div>
                                </div>
                                @empty
                                <p class="no-media">No media uploaded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Include TinyMCE or your preferred WYSIWYG -->

<script>
    $(document).ready(function() {
        
        // Save text for stage (AJAX)
        $('.stage-text-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var stage = form.data('stage');
            var content = form.find('[name="content"]').val();
            var contentType = form.find('[name="content_type"]').val();

            $.ajax({
                url: '{{ route("admin.lessons.stages.text", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    content: content,
                    content_type: contentType
                },
                success: function(response) {
                    alert('Content saved successfully!');
                },
                error: function(xhr) {
                    alert('Error saving content: ' + xhr.responseJSON.error);
                }
            });
        });

        // Upload media (AJAX)
        $('.media-upload-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var stage = form.data('stage');
            var formData = new FormData(this);

            $.ajax({
                url: '{{ route("admin.lessons.stages.media.store", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    // Add uploaded media to list
                    var mediaList = $('.media-list[data-stage="' + stage + '"]');
                    mediaList.find('.no-media').remove(); // remove placeholder if exists

                    var mediaHtml = '<div class="media-item d-flex align-items-center justify-content-between p-2 border mb-2" data-id="' + response.media.id + '">' +
                        '<div><strong>' + response.media.file_name + '</strong> <span class="badge bg-info">' + response.media.media_type + '</span>' +
                        (response.media.title ? '<br><small>Title: ' + response.media.title + '</small>' : '') + '</div>' +
                        '<div>' +
                        '<a href="' + response.media.url + '" target="_blank" class="btn btn-sm btn-secondary">View</a> ' +
                        '<button class="btn btn-sm btn-danger delete-media" data-id="' + response.media.id + '" data-stage="' + stage + '">Delete</button>' +
                        '</div></div>';

                    mediaList.append(mediaHtml);
                    form[0].reset(); // reset form
                    alert('File uploaded successfully!');
                },
                error: function(xhr) {
                    alert('Upload failed: ' + (xhr.responseJSON.error || 'Unknown error'));
                }
            });
        });

        // Delete media
        $(document).on('click', '.delete-media', function() {
            var btn = $(this);
            var mediaId = btn.data('id');
            var stage = btn.data('stage');
            if (!confirm('Delete this media?')) return;

            $.ajax({
                url: '{{ route("admin.lessons.stages.media.destroy", ["lesson" => $lesson->id, "stage" => "_stage_", "media" => "_mediaId_"]) }}'.replace('_stage_', stage).replace('_mediaId_', mediaId),
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    btn.closest('.media-item').remove();
                    // Show no-media message if list empty
                    var mediaList = btn.closest('.media-list');
                    if (mediaList.find('.media-item').length === 0) {
                        mediaList.append('<p class="no-media">No media uploaded yet.</p>');
                    }
                    alert('Media deleted.');
                },
                error: function() {
                    alert('Error deleting media.');
                }
            });
        });
    });
</script>
@endpush