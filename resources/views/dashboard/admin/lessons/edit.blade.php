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
                                @if($stage === 'engage')
                                    <div class="mb-3">
                                        <label class="form-label">Engage Activity Mode</label>
                                        <select name="activity_mode" class="form-control engage-activity-mode" data-stage="{{ $stage }}">
                                            <option value="chat" {{ ($stageData[$stage]['content']->activity_mode ?? 'chat') === 'chat' ? 'selected' : '' }}>AI Chat</option>
                                            <option value="mcq" {{ ($stageData[$stage]['content']->activity_mode ?? 'chat') === 'mcq' ? 'selected' : '' }}>Multiple Choice Question</option>
                                        </select>
                                        <div class="form-text">Choose whether students discuss with Denzy or answer a teacher-authored checkpoint question.</div>
                                    </div>
                                @endif
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

                    @if($stage === 'engage')
                        <div class="card mb-3 engage-mcq-config {{ ($stageData[$stage]['content']->activity_mode ?? 'chat') === 'mcq' ? '' : 'd-none' }}" data-stage="{{ $stage }}">
                            <div class="card-header">Engage Multiple Choice Question</div>
                            <div class="card-body">
                                <form class="engage-mcq-form" data-stage="{{ $stage }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label">Question</label>
                                        <textarea class="form-control" name="question" rows="3" placeholder="Enter the engage checkpoint question" required>{{ $stageData[$stage]['engageMcq']->question ?? '' }}</textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Option A</label>
                                            <input type="text" class="form-control" name="option_a" value="{{ $stageData[$stage]['engageMcq']->option_a ?? '' }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Feedback for Option A</label>
                                            <textarea class="form-control" name="feedback_option_a" rows="2" placeholder="Explain what this answer reveals and what the learner should carry into Explain.">{{ $stageData[$stage]['engageMcq']->feedback_option_a ?? '' }}</textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Option B</label>
                                            <input type="text" class="form-control" name="option_b" value="{{ $stageData[$stage]['engageMcq']->option_b ?? '' }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Feedback for Option B</label>
                                            <textarea class="form-control" name="feedback_option_b" rows="2">{{ $stageData[$stage]['engageMcq']->feedback_option_b ?? '' }}</textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Option C</label>
                                            <input type="text" class="form-control" name="option_c" value="{{ $stageData[$stage]['engageMcq']->option_c ?? '' }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Feedback for Option C</label>
                                            <textarea class="form-control" name="feedback_option_c" rows="2">{{ $stageData[$stage]['engageMcq']->feedback_option_c ?? '' }}</textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Option D</label>
                                            <input type="text" class="form-control" name="option_d" value="{{ $stageData[$stage]['engageMcq']->option_d ?? '' }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Feedback for Option D</label>
                                            <textarea class="form-control" name="feedback_option_d" rows="2">{{ $stageData[$stage]['engageMcq']->feedback_option_d ?? '' }}</textarea>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Correct Option</label>
                                            <select name="correct_option" class="form-control" required>
                                                <option value="a" {{ ($stageData[$stage]['engageMcq']->correct_option ?? '') === 'a' ? 'selected' : '' }}>Option A</option>
                                                <option value="b" {{ ($stageData[$stage]['engageMcq']->correct_option ?? '') === 'b' ? 'selected' : '' }}>Option B</option>
                                                <option value="c" {{ ($stageData[$stage]['engageMcq']->correct_option ?? '') === 'c' ? 'selected' : '' }}>Option C</option>
                                                <option value="d" {{ ($stageData[$stage]['engageMcq']->correct_option ?? '') === 'd' ? 'selected' : '' }}>Option D</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8 mb-3 d-flex align-items-end gap-2">
                                            <button type="submit" class="btn btn-primary">Save Engage MCQ</button>
                                            <button type="button" class="btn btn-outline-danger delete-engage-mcq {{ $stageData[$stage]['engageMcq'] ? '' : 'd-none' }}" data-stage="{{ $stage }}">Delete MCQ</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

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
                                        <button class="btn btn-sm btn-danger delete-media" data-id="{{ $media->id }}" data-stage="{{ $stage }}">Delete</button>
                                    </div>
                                </div>
                                @empty
                                <p class="no-media">No media uploaded yet.</p>
                                @endforelse
                            </div>

                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header">Misconception List</div>
                        <div class="card-body">
                            <form class="misconception-form" data-stage="{{ $stage }}">
                                @csrf
                                <input type="hidden" name="misconception_id" value="">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Concept Tag</label>
                                        <input type="text" name="concept_tag" class="form-control" placeholder="e.g. gravity">
                                    </div>
                                    <div class="col-md-5 mb-3">
                                        <label class="form-label">Misconception Label</label>
                                        <input type="text" name="label" class="form-control" placeholder="Common wrong idea" required>
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-control">
                                            <option value="approved">Approved</option>
                                            <option value="pending_review">Pending review</option>
                                            <option value="rejected">Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 mb-3 d-flex align-items-end">
                                        <button type="submit" class="btn btn-warning w-100 misconception-submit">Add Item</button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="2" placeholder="Describe the misconception students may show."></textarea>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Correct Concept</label>
                                        <textarea name="correct_concept" class="form-control" rows="2" placeholder="What is correct instead?"></textarea>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Remediation Hint</label>
                                        <textarea name="remediation_hint" class="form-control" rows="2" placeholder="Short hint for recovery."></textarea>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm misconception-cancel d-none">Cancel Edit</button>
                                </div>
                            </form>

                            <div class="misconception-list mt-3" data-stage="{{ $stage }}">
                                @forelse($stageData[$stage]['misconceptions'] as $misconception)
                                <div class="misconception-item border rounded p-3 mb-2"
                                    data-id="{{ $misconception->id }}"
                                    data-stage="{{ $stage }}"
                                    data-concept-tag="{{ e($misconception->concept_tag ?? '') }}"
                                    data-label="{{ e($misconception->label) }}"
                                    data-description="{{ e($misconception->description ?? '') }}"
                                    data-correct-concept="{{ e($misconception->correct_concept ?? '') }}"
                                    data-remediation-hint="{{ e($misconception->remediation_hint ?? '') }}"
                                    data-status="{{ e($misconception->status) }}"
                                    data-source="{{ e($misconception->source) }}">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div>
                                            <strong>{{ $misconception->label }}</strong>
                                            <span class="badge bg-warning text-dark ms-2">{{ $misconception->status }}</span>
                                            @if($misconception->concept_tag)
                                                <span class="badge bg-secondary ms-1">{{ $misconception->concept_tag }}</span>
                                            @endif
                                            @if($misconception->description)
                                                <div class="small text-muted mt-1">{{ $misconception->description }}</div>
                                            @endif
                                            @if($misconception->correct_concept)
                                                <div class="small mt-2"><strong>Correct concept:</strong> {{ $misconception->correct_concept }}</div>
                                            @endif
                                            @if($misconception->remediation_hint)
                                                <div class="small"><strong>Hint:</strong> {{ $misconception->remediation_hint }}</div>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-column align-items-end gap-2">
                                            <span class="badge bg-info text-dark">{{ $misconception->source }}</span>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-misconception">Edit</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-misconception">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                <p class="no-misconception text-muted mb-0">No misconception items yet.</p>
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
        function renderMisconceptionItem(misconception, stage) {
            return '<div class="misconception-item border rounded p-3 mb-2" ' +
                'data-id="' + misconception.id + '" ' +
                'data-stage="' + escapeHtml(stage) + '" ' +
                'data-concept-tag="' + escapeHtml(misconception.concept_tag || '') + '" ' +
                'data-label="' + escapeHtml(misconception.label) + '" ' +
                'data-description="' + escapeHtml(misconception.description || '') + '" ' +
                'data-correct-concept="' + escapeHtml(misconception.correct_concept || '') + '" ' +
                'data-remediation-hint="' + escapeHtml(misconception.remediation_hint || '') + '" ' +
                'data-status="' + escapeHtml(misconception.status) + '" ' +
                'data-source="' + escapeHtml(misconception.source) + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-3">' +
                '<div>' +
                '<strong>' + escapeHtml(misconception.label) + '</strong>' +
                '<span class="badge bg-warning text-dark ms-2">' + escapeHtml(misconception.status) + '</span>' +
                (misconception.concept_tag ? '<span class="badge bg-secondary ms-1">' + escapeHtml(misconception.concept_tag) + '</span>' : '') +
                (misconception.description ? '<div class="small text-muted mt-1">' + escapeHtml(misconception.description) + '</div>' : '') +
                (misconception.correct_concept ? '<div class="small mt-2"><strong>Correct concept:</strong> ' + escapeHtml(misconception.correct_concept) + '</div>' : '') +
                (misconception.remediation_hint ? '<div class="small"><strong>Hint:</strong> ' + escapeHtml(misconception.remediation_hint) + '</div>' : '') +
                '</div>' +
                '<div class="d-flex flex-column align-items-end gap-2">' +
                '<span class="badge bg-info text-dark">' + escapeHtml(misconception.source) + '</span>' +
                '<div class="d-flex gap-2">' +
                '<button type="button" class="btn btn-sm btn-outline-primary edit-misconception">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger delete-misconception">Delete</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }

        function resetMisconceptionForm(form) {
            form[0].reset();
            form.find('[name="misconception_id"]').val('');
            form.find('[name="status"]').val('approved');
            form.find('.misconception-submit').text('Add Item');
            form.find('.misconception-cancel').addClass('d-none');
        }

        function toggleEngageMcqConfig(mode) {
            var target = $('.engage-mcq-config[data-stage="engage"]');

            if (!target.length) {
                return;
            }

            target.toggleClass('d-none', mode !== 'mcq');
        }

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }
        
        // Save text for stage (AJAX)
        $('.stage-text-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var stage = form.data('stage');
            var content = form.find('[name="content"]').val();
            var contentType = form.find('[name="content_type"]').val();
            var activityMode = form.find('[name="activity_mode"]').val();

            $.ajax({
                url: '{{ route("admin.lessons.stages.text", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    content: content,
                    content_type: contentType,
                    activity_mode: activityMode
                },
                success: function(response) {
                    alert('Content saved successfully!');
                },
                error: function(xhr) {
                    alert('Error saving content: ' + xhr.responseJSON.error);
                }
            });
        });

        $('.engage-activity-mode').on('change', function() {
            toggleEngageMcqConfig($(this).val());
        });

        $('.engage-mcq-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var stage = form.data('stage');

            $.ajax({
                url: '{{ route("admin.lessons.stages.engage-mcq.upsert", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: form.serialize(),
                success: function() {
                    $('.engage-activity-mode[data-stage="' + stage + '"]').val('mcq');
                    toggleEngageMcqConfig('mcq');
                    $('.delete-engage-mcq[data-stage="' + stage + '"]').removeClass('d-none');
                    alert('Engage MCQ saved successfully.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error saving Engage MCQ: ' + message);
                }
            });
        });

        $('.delete-engage-mcq').on('click', function() {
            var button = $(this);
            var stage = button.data('stage');
            var form = $('.engage-mcq-form[data-stage="' + stage + '"]');

            if (!confirm('Delete this Engage MCQ?')) {
                return;
            }

            $.ajax({
                url: '{{ route("admin.lessons.stages.engage-mcq.destroy", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function() {
                    form[0].reset();
                    button.addClass('d-none');
                    alert('Engage MCQ deleted.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error deleting Engage MCQ: ' + message);
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

        $('.misconception-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var stage = form.data('stage');
            var misconceptionId = form.find('[name="misconception_id"]').val();
            var isEditing = !!misconceptionId;
            var url = isEditing
                ? '{{ route("admin.lessons.stages.misconceptions.update", ["lesson" => $lesson->id, "stage" => "_stage_", "misconception" => "_misconception_"]) }}'.replace('_stage_', stage).replace('_misconception_', misconceptionId)
                : '{{ route("admin.lessons.stages.misconceptions.store", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage);
            var payload = form.serializeArray();

            if (isEditing) {
                payload.push({ name: '_method', value: 'PATCH' });
            }

            $.ajax({
                url: url,
                method: 'POST',
                data: $.param(payload),
                success: function(response) {
                    var misconception = response.data;
                    var list = $('.misconception-list[data-stage="' + stage + '"]');
                    list.find('.no-misconception').remove();

                    if (isEditing) {
                        list.find('.misconception-item[data-id="' + misconception.id + '"]').replaceWith(renderMisconceptionItem(misconception, stage));
                    } else {
                        list.prepend(renderMisconceptionItem(misconception, stage));
                    }

                    resetMisconceptionForm(form);
                    alert(isEditing ? 'Misconception item updated.' : 'Misconception item saved.');
                },
                error: function(xhr) {
                    var message = 'Unknown error';
                    if (xhr.responseJSON) {
                        message = xhr.responseJSON.message || xhr.responseJSON.error || 'Validation failed';
                    }
                    alert('Error saving misconception: ' + message);
                }
            });
        });

        $(document).on('click', '.edit-misconception', function() {
            var item = $(this).closest('.misconception-item');
            var stage = item.data('stage');
            var form = $('.misconception-form[data-stage="' + stage + '"]');

            form.find('[name="misconception_id"]').val(item.data('id'));
            form.find('[name="concept_tag"]').val(item.data('concept-tag'));
            form.find('[name="label"]').val(item.data('label'));
            form.find('[name="description"]').val(item.data('description'));
            form.find('[name="correct_concept"]').val(item.data('correct-concept'));
            form.find('[name="remediation_hint"]').val(item.data('remediation-hint'));
            form.find('[name="status"]').val(item.data('status'));
            form.find('.misconception-submit').text('Update Item');
            form.find('.misconception-cancel').removeClass('d-none');

            $('html, body').animate({
                scrollTop: form.offset().top - 120
            }, 200);
        });

        $('.misconception-cancel').on('click', function() {
            resetMisconceptionForm($(this).closest('.misconception-form'));
        });

        $(document).on('click', '.delete-misconception', function() {
            var item = $(this).closest('.misconception-item');
            var stage = item.data('stage');
            var id = item.data('id');

            if (!confirm('Delete this misconception item?')) {
                return;
            }

            $.ajax({
                url: '{{ route("admin.lessons.stages.misconceptions.destroy", ["lesson" => $lesson->id, "stage" => "_stage_", "misconception" => "_misconception_"]) }}'.replace('_stage_', stage).replace('_misconception_', id),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function() {
                    var list = item.closest('.misconception-list');
                    item.remove();
                    if (list.find('.misconception-item').length === 0) {
                        list.append('<p class="no-misconception text-muted mb-0">No misconception items yet.</p>');
                    }
                    resetMisconceptionForm($('.misconception-form[data-stage="' + stage + '"]'));
                    alert('Misconception item deleted.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error deleting misconception: ' + message);
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

        toggleEngageMcqConfig($('.engage-activity-mode[data-stage="engage"]').val());
    });
</script>
@endpush