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
                            <div class="col-md-6 mb-3">
                                <label for="teacher_id" class="form-label">Teacher Owner</label>
                                <select class="form-control" id="teacher_id" name="teacher_id">
                                    <option value="">Unassigned</option>
                                    @foreach($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" {{ (string) $lesson->teacher_id === (string) $teacher->id ? 'selected' : '' }}>{{ $teacher->name }}</option>
                                    @endforeach
                                </select>
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

                    @if(in_array($stage, ['explore', 'explain', 'elaborate']))
                        <div class="card mb-3 checkpoint-config" data-stage="{{ $stage }}">
                            <div class="card-header">AI Checkpoint</div>
                            <div class="card-body">
                                <p class="text-muted small mb-3">Add teacher-authored checkpoint questions and a stage-only corpus for AI evaluation. The uploaded corpus is used only for this checkpoint stage.</p>

                                <div class="row">
                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Checkpoint Questions</h5>
                                        <form class="checkpoint-question-form" data-stage="{{ $stage }}">
                                            @csrf
                                            <input type="hidden" name="question_id" value="">
                                            <div class="mb-3">
                                                <label class="form-label">Question</label>
                                                <textarea class="form-control" name="question_text" rows="3" placeholder="Enter a short checkpoint question" required></textarea>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Order</label>
                                                    <input type="number" class="form-control" name="sort_order" min="1" value="1">
                                                </div>
                                                <div class="col-md-4 mb-3 d-flex align-items-end">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="checkpoint_active_{{ $stage }}" checked>
                                                        <label class="form-check-label" for="checkpoint_active_{{ $stage }}">Active</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 mb-3 d-flex align-items-end gap-2">
                                                    <button type="submit" class="btn btn-primary checkpoint-question-submit">Save Question</button>
                                                    <button type="button" class="btn btn-outline-secondary checkpoint-question-cancel d-none">Cancel</button>
                                                </div>
                                            </div>
                                        </form>

                                        <div class="checkpoint-question-list mt-3" data-stage="{{ $stage }}">
                                            @forelse($stageData[$stage]['checkpointQuestions'] as $question)
                                                <div class="checkpoint-question-item border rounded p-3 mb-2"
                                                    data-id="{{ $question->id }}"
                                                    data-stage="{{ $stage }}"
                                                    data-question-text="{{ e($question->question_text) }}"
                                                    data-sort-order="{{ $question->sort_order }}"
                                                    data-is-active="{{ $question->is_active ? '1' : '0' }}">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <div class="fw-semibold">{{ $question->question_text }}</div>
                                                            <div class="small text-muted mt-1">Order: {{ $question->sort_order }}</div>
                                                        </div>
                                                        <div class="d-flex flex-column align-items-end gap-2">
                                                            <span class="badge {{ $question->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $question->is_active ? 'active' : 'inactive' }}</span>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-outline-primary edit-checkpoint-question">Edit</button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-question">Delete</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="no-checkpoint-question text-muted mb-0">No checkpoint questions yet.</p>
                                            @endforelse
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <h5 class="mb-3">Checkpoint Corpus</h5>
                                        <form class="checkpoint-corpus-form" data-stage="{{ $stage }}" enctype="multipart/form-data">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-7 mb-3">
                                                    <label class="form-label">File</label>
                                                    <input type="file" name="file" class="form-control" accept=".pdf,.txt,.md" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" name="title" class="form-control" placeholder="Optional label">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea name="description" class="form-control" rows="2" placeholder="Optional notes for this corpus file"></textarea>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Order</label>
                                                    <input type="number" name="sort_order" class="form-control" min="1" value="1">
                                                </div>
                                                <div class="col-md-2 mb-3 d-flex align-items-end">
                                                    <button type="submit" class="btn btn-success w-100">Upload</button>
                                                </div>
                                            </div>
                                        </form>

                                        <div class="checkpoint-corpus-list mt-3" data-stage="{{ $stage }}">
                                            @forelse($stageData[$stage]['checkpointCorpora'] as $corpus)
                                                <div class="checkpoint-corpus-item border rounded p-3 mb-2" data-id="{{ $corpus->id }}" data-stage="{{ $stage }}" data-status="{{ $corpus->processing_status }}">
                                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                                        <div>
                                                            <div class="fw-semibold">{{ $corpus->title ?: $corpus->file_name }}</div>
                                                            <div class="small text-muted">{{ $corpus->file_name }} • {{ strtoupper($corpus->file_type) }} • Order: {{ $corpus->sort_order }}</div>
                                                            @if($corpus->description)
                                                                <div class="small text-muted mt-1">{{ $corpus->description }}</div>
                                                            @endif
                                                            @if($corpus->error_message)
                                                                <div class="small text-danger mt-1">{{ $corpus->error_message }}</div>
                                                            @endif
                                                        </div>
                                                        <div class="d-flex flex-column align-items-end gap-2">
                                                            <span class="badge corpus-status-badge {{ $corpus->processing_status === 'completed' ? 'bg-success' : ($corpus->processing_status === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $corpus->processing_status }}</span>
                                                            <div class="d-flex gap-2 corpus-actions" style="flex-wrap: wrap; justify-content: flex-end;">
                                                                @if($corpus->processing_status === 'completed')
                                                                    <a href="{{ $corpus->url }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                                                                @endif
                                                                @if($corpus->processing_status === 'failed')
                                                                    <button type="button" class="btn btn-sm btn-outline-warning reprocess-checkpoint-corpus" title="Retry this corpus upload">Retry</button>
                                                                @endif
                                                                <button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-corpus">Delete</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="no-checkpoint-corpus text-muted mb-0">No checkpoint corpus uploaded yet.</p>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
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
        function renderCheckpointQuestionItem(question, stage) {
            return '<div class="checkpoint-question-item border rounded p-3 mb-2" ' +
                'data-id="' + question.id + '" ' +
                'data-stage="' + escapeHtml(stage) + '" ' +
                'data-question-text="' + escapeHtml(question.question_text) + '" ' +
                'data-sort-order="' + escapeHtml(String(question.sort_order || 1)) + '" ' +
                'data-is-active="' + (question.is_active ? '1' : '0') + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-3">' +
                '<div>' +
                '<div class="fw-semibold">' + escapeHtml(question.question_text) + '</div>' +
                '<div class="small text-muted mt-1">Order: ' + escapeHtml(String(question.sort_order || 1)) + '</div>' +
                '</div>' +
                '<div class="d-flex flex-column align-items-end gap-2">' +
                '<span class="badge ' + (question.is_active ? 'bg-success' : 'bg-secondary') + '">' + (question.is_active ? 'active' : 'inactive') + '</span>' +
                '<div class="d-flex gap-2">' +
                '<button type="button" class="btn btn-sm btn-outline-primary edit-checkpoint-question">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-question">Delete</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }

        function renderCheckpointCorpusItem(corpus, stage) {
            var statusClass = corpus.processing_status === 'completed'
                ? 'bg-success'
                : (corpus.processing_status === 'failed' ? 'bg-danger' : 'bg-warning text-dark');

            var actions = '';
            if (corpus.processing_status === 'completed') {
                actions += '<a href="' + escapeHtml(corpus.url) + '" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>';
            }
            if (corpus.processing_status === 'failed') {
                actions += '<button type="button" class="btn btn-sm btn-outline-warning reprocess-checkpoint-corpus" title="Retry this corpus upload">Retry</button>';
            }
            actions += '<button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-corpus">Delete</button>';

            return '<div class="checkpoint-corpus-item border rounded p-3 mb-2" data-id="' + corpus.id + '" data-stage="' + escapeHtml(stage) + '" data-status="' + escapeHtml(corpus.processing_status) + '">' +
                '<div class="d-flex justify-content-between align-items-start gap-3">' +
                '<div>' +
                '<div class="fw-semibold">' + escapeHtml(corpus.title || corpus.file_name) + '</div>' +
                '<div class="small text-muted">' + escapeHtml(corpus.file_name) + ' • ' + escapeHtml((corpus.file_type || '').toUpperCase()) + ' • Order: ' + escapeHtml(String(corpus.sort_order || 1)) + '</div>' +
                (corpus.description ? '<div class="small text-muted mt-1">' + escapeHtml(corpus.description) + '</div>' : '') +
                (corpus.error_message ? '<div class="small text-danger mt-1">' + escapeHtml(corpus.error_message) + '</div>' : '') +
                '</div>' +
                '<div class="d-flex flex-column align-items-end gap-2">' +
                '<span class="badge corpus-status-badge ' + statusClass + '">' + escapeHtml(corpus.processing_status) + '</span>' +
                '<div class="d-flex gap-2 corpus-actions" style="flex-wrap: wrap; justify-content: flex-end;">' +
                actions +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }

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

        function resetCheckpointQuestionForm(form) {
            form[0].reset();
            form.find('[name="question_id"]').val('');
            form.find('[name="sort_order"]').val('1');
            form.find('[name="is_active"]').prop('checked', true);
            form.find('.checkpoint-question-submit').text('Save Question');
            form.find('.checkpoint-question-cancel').addClass('d-none');
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

        $('.checkpoint-question-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var stage = form.data('stage');
            var questionId = form.find('[name="question_id"]').val();
            var isEditing = !!questionId;
            var url = isEditing
                ? '{{ route("admin.lessons.stages.checkpoint-questions.update", ["lesson" => $lesson->id, "stage" => "_stage_", "question" => "_question_"]) }}'.replace('_stage_', stage).replace('_question_', questionId)
                : '{{ route("admin.lessons.stages.checkpoint-questions.store", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage);
            var payload = form.serializeArray();

            if (isEditing) {
                payload.push({ name: '_method', value: 'PATCH' });
            }

            $.ajax({
                url: url,
                method: 'POST',
                data: $.param(payload),
                success: function(response) {
                    var question = response.data;
                    var list = $('.checkpoint-question-list[data-stage="' + stage + '"]');
                    list.find('.no-checkpoint-question').remove();

                    if (isEditing) {
                        list.find('.checkpoint-question-item[data-id="' + question.id + '"]').replaceWith(renderCheckpointQuestionItem(question, stage));
                    } else {
                        list.prepend(renderCheckpointQuestionItem(question, stage));
                    }

                    resetCheckpointQuestionForm(form);
                    alert(isEditing ? 'Checkpoint question updated.' : 'Checkpoint question saved.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error saving checkpoint question: ' + message);
                }
            });
        });

        $(document).on('click', '.edit-checkpoint-question', function() {
            var item = $(this).closest('.checkpoint-question-item');
            var stage = item.data('stage');
            var form = $('.checkpoint-question-form[data-stage="' + stage + '"]');

            form.find('[name="question_id"]').val(item.data('id'));
            form.find('[name="question_text"]').val(item.data('question-text'));
            form.find('[name="sort_order"]').val(item.data('sort-order'));
            form.find('[name="is_active"]').prop('checked', String(item.data('is-active')) === '1');
            form.find('.checkpoint-question-submit').text('Update Question');
            form.find('.checkpoint-question-cancel').removeClass('d-none');

            $('html, body').animate({
                scrollTop: form.offset().top - 120
            }, 200);
        });

        $('.checkpoint-question-cancel').on('click', function() {
            resetCheckpointQuestionForm($(this).closest('.checkpoint-question-form'));
        });

        $(document).on('click', '.delete-checkpoint-question', function() {
            var item = $(this).closest('.checkpoint-question-item');
            var stage = item.data('stage');
            var questionId = item.data('id');

            if (!confirm('Delete this checkpoint question?')) {
                return;
            }

            $.ajax({
                url: '{{ route("admin.lessons.stages.checkpoint-questions.destroy", ["lesson" => $lesson->id, "stage" => "_stage_", "question" => "_question_"]) }}'.replace('_stage_', stage).replace('_question_', questionId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function(response) {
                    var list = item.closest('.checkpoint-question-list');
                    item.remove();

                    if (list.find('.checkpoint-question-item').length === 0) {
                        list.append('<p class="no-checkpoint-question text-muted mb-0">No checkpoint questions yet.</p>');
                    }

                    resetCheckpointQuestionForm($('.checkpoint-question-form[data-stage="' + stage + '"]'));
                    alert(response.message || 'Checkpoint question deleted.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error deleting checkpoint question: ' + message);
                }
            });
        });

        $('.checkpoint-corpus-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var stage = form.data('stage');
            var formData = new FormData(this);

            $.ajax({
                url: '{{ route("admin.lessons.stages.checkpoint-corpus.store", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    var corpus = response.data;
                    var list = $('.checkpoint-corpus-list[data-stage="' + stage + '"]');
                    list.find('.no-checkpoint-corpus').remove();
                    list.prepend(renderCheckpointCorpusItem(corpus, stage));
                    form[0].reset();
                    form.find('[name="sort_order"]').val('1');
                    alert(response.message || 'Checkpoint corpus uploaded.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error uploading checkpoint corpus: ' + message);
                }
            });
        });

        $(document).on('click', '.delete-checkpoint-corpus', function() {
            var item = $(this).closest('.checkpoint-corpus-item');
            var stage = item.data('stage');
            var corpusId = item.data('id');

            if (!confirm('Delete this checkpoint corpus file?')) {
                return;
            }

            $.ajax({
                url: '{{ route("admin.lessons.stages.checkpoint-corpus.destroy", ["lesson" => $lesson->id, "stage" => "_stage_", "corpus" => "_corpus_"]) }}'.replace('_stage_', stage).replace('_corpus_', corpusId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    _method: 'DELETE'
                },
                success: function(response) {
                    var list = item.closest('.checkpoint-corpus-list');
                    item.remove();

                    if (list.find('.checkpoint-corpus-item').length === 0) {
                        list.append('<p class="no-checkpoint-corpus text-muted mb-0">No checkpoint corpus uploaded yet.</p>');
                    }

                    alert(response.message || 'Checkpoint corpus deleted.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error deleting checkpoint corpus: ' + message);
                }
            });
        });

        $(document).on('click', '.reprocess-checkpoint-corpus', function() {
            var item = $(this).closest('.checkpoint-corpus-item');
            var stage = item.data('stage');
            var corpusId = item.data('id');
            var button = $(this);

            if (!confirm('Reprocess this checkpoint corpus embedding? This will retry based on the uploaded file.')) {
                return;
            }

            button.prop('disabled', true).text('Reprocessing...');

            $.ajax({
                url: '{{ route("admin.lessons.stages.checkpoint-corpus.reprocess", ["lesson" => $lesson->id, "stage" => "_stage_", "corpus" => "_corpus_"]) }}'.replace('_stage_', stage).replace('_corpus_', corpusId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                },
                success: function(response) {
                    var corpus = response.data;
                    item.replaceWith(renderCheckpointCorpusItem(corpus, stage));
                    alert(response.message || 'Checkpoint corpus reprocessing started.');
                    // Start polling for status updates
                    pollCorpusStatus(stage, corpusId);
                },
                error: function(xhr) {
                    button.prop('disabled', false).text('Retry');
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'Unknown error';
                    alert('Error reprocessing checkpoint corpus: ' + message);
                }
            });
        });

        // Polling for checkpoint corpus status updates
        var corpusPollingIntervals = {};

        function pollCorpusStatus(stage, corpusId, maxAttempts = 60) {
            if (corpusPollingIntervals[corpusId]) {
                clearInterval(corpusPollingIntervals[corpusId]);
            }

            var attempts = 0;

            corpusPollingIntervals[corpusId] = setInterval(function() {
                attempts++;

                $.ajax({
                    url: '{{ route("admin.lessons.stages.checkpoint-corpus.status", ["lesson" => $lesson->id, "stage" => "_stage_", "corpus" => "_corpus_"]) }}'.replace('_stage_', stage).replace('_corpus_', corpusId),
                    method: 'GET',
                    success: function(response) {
                        var item = $('.checkpoint-corpus-item[data-id="' + corpusId + '"][data-stage="' + stage + '"]');
                        if (!item.length) return;

                        var currentStatus = item.data('status');
                        var newStatus = response.processing_status;

                        // Update status badge
                        var statusBadge = item.find('.corpus-status-badge');
                        var statusClass = newStatus === 'completed'
                            ? 'bg-success'
                            : (newStatus === 'failed' ? 'bg-danger' : 'bg-warning text-dark');

                        statusBadge.removeClass('bg-success bg-danger bg-warning text-dark')
                            .addClass(statusClass)
                            .text(newStatus);

                        item.data('status', newStatus);

                        // Update actions buttons
                        var actions = item.find('.corpus-actions');
                        if (newStatus === 'completed') {
                            actions.html('<a href="' + escapeHtml(response.url) + '" target="_blank" class="btn btn-sm btn-outline-secondary">View</a><button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-corpus">Delete</button>');
                        } else if (newStatus === 'failed') {
                            actions.html('<button type="button" class="btn btn-sm btn-outline-warning reprocess-checkpoint-corpus" title="Retry this corpus upload">Retry</button><button type="button" class="btn btn-sm btn-outline-danger delete-checkpoint-corpus">Delete</button>');
                            if (response.error_message) {
                                var errorDiv = item.find('.small.text-danger');
                                if (errorDiv.length) {
                                    errorDiv.text(response.error_message);
                                } else {
                                    item.find('div').first().append('<div class="small text-danger mt-1">' + escapeHtml(response.error_message) + '</div>');
                                }
                            }
                        }

                        // Stop polling when done
                        if (newStatus === 'completed' || newStatus === 'failed' || attempts >= maxAttempts) {
                            clearInterval(corpusPollingIntervals[corpusId]);
                            delete corpusPollingIntervals[corpusId];
                        }
                    }
                });
            }, 2000); // Poll every 2 seconds
        }

        // Auto-start polling for any pending corpus uploads on page load
        $.each(['explore', 'explain', 'elaborate'], function(_, stage) {
            $('.checkpoint-corpus-item[data-stage="' + stage + '"]').each(function() {
                var status = $(this).data('status');
                var corpusId = $(this).data('id');
                if (status === 'pending' || status === 'processing') {
                    pollCorpusStatus(stage, corpusId);
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
                    alert(response.message || 'File uploaded successfully!');
                },
                error: function(xhr) {
                    console.log(xhr);
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                        ? (xhr.responseJSON.message || xhr.responseJSON.error)
                        : 'Unknown error';
                    alert('Upload failed: ' + message);
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
                    alert(response.message || 'Media deleted.');
                },
                error: function(xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                        ? (xhr.responseJSON.message || xhr.responseJSON.error)
                        : 'Unknown error';
                    alert('Error deleting media: ' + message);
                }
            });
        });

        toggleEngageMcqConfig($('.engage-activity-mode[data-stage="engage"]').val());
    });
</script>
@endpush