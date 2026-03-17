@extends('layout.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1>{!!  $lesson->title !!}</h1>
            <p>{!! $lesson->description !!}</p>
        </div>
    </div>

    <!-- 5E Tabs -->
    <ul class="nav nav-tabs" id="lessonTabs" role="tablist">
        @foreach($stages as $index => $stage)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $index == 0 ? 'active' : '' }} {{ $progress->{$stage.'_completed'} ? 'bg-success text-white' : '' }}" 
                    id="{{ $stage }}-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#{{ $stage }}" 
                    type="button" 
                    role="tab" 
                    aria-controls="{{ $stage }}" 
                    aria-selected="{{ $index == 0 ? 'true' : 'false' }}">
                {{ ucfirst($stage) }}
                @if($progress->{$stage.'_completed'})
                    <i class="fas fa-check-circle ms-2"></i>
                @endif
            </button>
        </li>
        @endforeach
    </ul>

    <div class="tab-content p-3 border border-top-0 bg-white" id="lessonTabsContent">
        @foreach($stages as $index => $stage)
        <div class="tab-pane fade {{ $index == 0 ? 'show active' : '' }}" id="{{ $stage }}" role="tabpanel" aria-labelledby="{{ $stage }}-tab">
            @if($stage === 'explain' && $engageMode === 'mcq' && $engageMcqAttempt?->resolved_feedback)
                <div class="alert alert-warning border-start border-4 border-warning">
                    <strong>From your Engage checkpoint:</strong>
                    <div class="mt-2">{!! nl2br(e($engageMcqAttempt->resolved_feedback)) !!}</div>
                </div>
            @endif

            <!-- Stage Content -->
            @if($stageData[$stage]['content'] && $stageData[$stage]['content']->content)
                <div class="mb-4">
                    {!! $stageData[$stage]['content']->content !!}
                </div>
            @endif

            <!-- Media Files -->
            @if($stageData[$stage]['media']->count() > 0)
                <div class="row mb-4">
                    @foreach($stageData[$stage]['media'] as $media)
                        <div class="col-md-8 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    @if($media->media_type == 'video')
                                        <video width="100%" controls>
                                            <source src="{{ $media->url }}" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    @elseif($media->media_type == 'image')
                                        <img src="{{ $media->url }}" class="img-fluid" alt="{{ $media->title }}">
                                    @elseif($media->media_type == 'pdf')
                                        <a href="{{ $media->url }}" target="_blank" class="btn btn-outline-primary">
                                            <i class="fas fa-file-pdf"></i> {{ $media->file_name }}
                                        </a>
                                    @elseif($media->media_type == 'phet_html')
                                        <iframe src="{{ $media->url }}" width="100%" height="400px" frameborder="0"></iframe>
                                    @endif
                                    @if($media->title)
                                        <p class="mt-2"><strong>{{ $media->title }}</strong></p>
                                    @endif
                                    @if($media->description)
                                        <p>{!! $media->description !!}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Evaluate Stage: Quiz -->
            @if($stage == 'evaluate' && $quizQuestions->count() > 0)
                <div class="card">
                    <div class="card-header">Lesson Quiz</div>
                    <div class="card-body">
                        <form id="quiz-form">
                            @csrf
                            @foreach($quizQuestions as $index => $question)
                                <div class="mb-4 p-3 border rounded">
                                    <p><strong>Q{{ $index+1 }}. {{ $question->question }}</strong></p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="a" id="q{{ $question->id }}_a" {{ isset($previousAttempts[$question->id]) && $previousAttempts[$question->id]->selected_option == 'a' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="q{{ $question->id }}_a">A. {{ $question->option_a }}</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="b" id="q{{ $question->id }}_b" {{ isset($previousAttempts[$question->id]) && $previousAttempts[$question->id]->selected_option == 'b' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="q{{ $question->id }}_b">B. {{ $question->option_b }}</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="c" id="q{{ $question->id }}_c" {{ isset($previousAttempts[$question->id]) && $previousAttempts[$question->id]->selected_option == 'c' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="q{{ $question->id }}_c">C. {{ $question->option_c }}</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="answers[{{ $question->id }}]" value="d" id="q{{ $question->id }}_d" {{ isset($previousAttempts[$question->id]) && $previousAttempts[$question->id]->selected_option == 'd' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="q{{ $question->id }}_d">D. {{ $question->option_d }}</label>
                                    </div>
                                    @if(isset($previousAttempts[$question->id]))
                                        <div class="mt-2">
                                            @if($previousAttempts[$question->id]->is_correct)
                                                <span class="badge bg-success">Previous answer correct</span>
                                            @else
                                                <span class="badge bg-danger">Previous answer incorrect</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                            <button type="submit" class="btn btn-primary">Submit Quiz</button>
                        </form>
                        <div id="quiz-result" class="mt-3"></div>
                    </div>
                </div>
            @endif

            @if($stage === 'engage' && $engageMode === 'chat')
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Engage Discussion</span>
                        <span class="badge bg-secondary" id="engage-status-badge">
                            {{ $canMarkEngageComplete ? 'Ready to complete' : 'Discussion in progress' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div id="engage-chat-messages" class="border rounded bg-light p-3 mb-3" style="max-height: 380px; overflow-y: auto;">
                            @forelse($engageMessages as $message)
                                @if($message->question && $message->question !== '__engage_start__')
                                    <div class="text-end mb-2">
                                        <span class="bg-primary text-white p-2 rounded d-inline-block">{!! nl2br(e($message->question)) !!}</span>
                                    </div>
                                @endif

                                <div class="text-start mb-2">
                                    <span class="bg-white border p-2 rounded d-inline-block">
                                        {!! nl2br(e($message->answer)) !!}
                                        @if($message->classification)
                                            <span class="d-block small text-muted mt-1">
                                                Class: {{ ucfirst(str_replace('_', ' ', $message->classification)) }}
                                                @if(!is_null($message->confidence))
                                                    ({{ (int) round($message->confidence * 100) }}%)
                                                @endif
                                            </span>
                                        @endif
                                        @if($message->engage_status)
                                            <span class="d-block small text-muted">Status: {{ ucfirst(str_replace('_', ' ', $message->engage_status)) }}</span>
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <div class="alert alert-info mb-0" id="engage-chat-placeholder">
                                    Denzy will start the Engage discussion here.
                                </div>
                            @endforelse
                        </div>

                        @if(!$progress->engage_completed)
                            <form id="engage-chat-form">
                                @csrf
                                <div class="mb-3">
                                    <label for="engage-chat-input" class="form-label">Your response</label>
                                    <textarea id="engage-chat-input" class="form-control" rows="4" placeholder="Respond to the scenario or Denzy's question here..." required></textarea>
                                </div>
                                <button class="btn btn-primary" type="submit">Send Response</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @if($stage === 'engage' && $engageMode === 'mcq' && $engageMcqQuestion)
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Engage Checkpoint</span>
                        <span class="badge {{ $progress->engage_completed ? 'bg-success' : 'bg-secondary' }}" id="engage-status-badge">
                            {{ $progress->engage_completed ? 'Complete' : 'Waiting for response' }}
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="fw-semibold mb-3">{{ $engageMcqQuestion->question }}</p>

                        @if(!$progress->engage_completed)
                            <form id="engage-mcq-form">
                                @csrf
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="selected_option" value="a" id="engage_option_a">
                                    <label class="form-check-label" for="engage_option_a">A. {{ $engageMcqQuestion->option_a }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="selected_option" value="b" id="engage_option_b">
                                    <label class="form-check-label" for="engage_option_b">B. {{ $engageMcqQuestion->option_b }}</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="selected_option" value="c" id="engage_option_c">
                                    <label class="form-check-label" for="engage_option_c">C. {{ $engageMcqQuestion->option_c }}</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="selected_option" value="d" id="engage_option_d">
                                    <label class="form-check-label" for="engage_option_d">D. {{ $engageMcqQuestion->option_d }}</label>
                                </div>
                                <button class="btn btn-primary" type="submit">Submit Checkpoint</button>
                            </form>
                        @endif

                        <div id="engage-mcq-result" class="{{ $engageMcqAttempt ? 'mt-3' : 'mt-0' }}">
                            @if($engageMcqAttempt)
                                <div class="alert {{ $engageMcqAttempt->is_correct ? 'alert-success' : 'alert-warning' }} mb-0">
                                    <div><strong>Your choice:</strong> {{ strtoupper($engageMcqAttempt->selected_option) }}</div>
                                    <div class="mt-2">{!! nl2br(e($engageMcqAttempt->resolved_feedback ?? '')) !!}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Mark as Complete Button (except evaluate if not done yet) -->
            @if(!$progress->{$stage.'_completed'} && $stage != 'evaluate')
                @if($stage === 'engage')
                    @if($engageMode === 'chat')
                        <div class="mb-2 small text-muted" id="engage-complete-helper">
                            @if($canMarkEngageComplete)
                                Denzy has marked this Engage discussion as ready for completion.
                            @else
                                Continue the Engage discussion until Denzy marks it ready for completion.
                            @endif
                        </div>
                        <button class="btn btn-success mark-complete" data-stage="{{ $stage }}" id="engage-complete-button" {{ $canMarkEngageComplete ? '' : 'disabled' }}>
                            Mark {{ ucfirst($stage) }} as Complete
                        </button>
                    @else
                        <p class="text-info mb-0">Submit the checkpoint above to complete the Engage stage.</p>
                    @endif
                @else
                    <button class="btn btn-success mark-complete" data-stage="{{ $stage }}">Mark {{ ucfirst($stage) }} as Complete</button>
                @endif
            @elseif($stage == 'evaluate' && $quizQuestions->count() > 0 && !$progress->evaluate_completed)
                <p class="text-info">Complete the quiz above to finish this lesson.</p>
            @elseif($stage == 'evaluate' && $progress->evaluate_completed)
                <div class="alert alert-success">You have completed the evaluation.</div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<!-- Floating AI Chat Button -->
<div id="ai-chat-widget" style="position: fixed; top: 80%; left: 90%; z-index: 1000;">
    <div style="width: 300px; position: absolute; top: -480px; left: -220px; overflow-x:hidden; display:none" id="ai-chat-container" class="card alert-danger border-2 rounded">
        <div class="bg-dark text-light text-center h6 m-0 p-3 border-bottom">
            <i class="fas fa-robot fa-2x me-3"></i>
            <strong>Denzy </strong>
            <button type="button" class="btn-danger btn-close float-end text-light" id="ai-chat-close"></button>
        </div>
            <div class="card-body" style="height: 400px; overflow-y: auto;" id="chat-messages">
                <div class="alert alert-info">Ask me anything about this lesson!</div>
            </div>
        <div class="card-footer">
                <form id="chat-form" class="w-100">
                    <div class="input-group">
                        <input type="text" id="chat-input" class="form-control" placeholder="Type your question..." required>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </div>
                </form>
            </div>
    </div>
    
    <button id="ai-chat-button" class="btn btn-dark rounded-circle" style="width: 60px; height: 60px;">
        <i class="fas fa-robot"></i>
    </button>
</div>

<!-- Floating AI Chat Button ->
<button id="ai-chat-button" class="btn btn-primary rounded-circle position-fixed bottom-0 end-0 m-4" style="width: 60px; height: 60px; z-index: 1000;">
    <i class="fas fa-robot"></i>
</button>

 AI Chat Modal ->
<div class="modal fade" id="aiChatModal" tabindex="-1" aria-labelledby="aiChatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiChatModalLabel">AI Tutor (Powered by OLLAMA)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="height: 400px; overflow-y: auto;" id="chat-messages">
                <div class="alert alert-info">Ask me anything about this lesson!</div>
            </div>
            <div class="modal-footer">
                <form id="chat-form" class="w-100">
                    <div class="input-group">
                        <input type="text" id="chat-input" class="form-control" placeholder="Type your question..." required>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div-->
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    var engageStarted = {{ $engageMessages->isNotEmpty() ? 'true' : 'false' }};
    var engageMode = '{{ $engageMode }}';

    function activeStage() {
        return $('.nav-link.active').data('bs-target').substring(1);
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function scrollToBottom(selector) {
        var container = $(selector);

        if (container.length) {
            container.scrollTop(container[0].scrollHeight);
        }
    }

    function updateDenzyVisibility() {
        if (activeStage() === 'engage') {
            $('#ai-chat-container').hide();
            $('#ai-chat-widget').hide();
            return;
        }

        $('#ai-chat-widget').show();
    }

    function updateEngageCompletionState(canComplete) {
        var helper = $('#engage-complete-helper');
        var badge = $('#engage-status-badge');
        var button = $('#engage-complete-button');

        if (!button.length) {
            return;
        }

        button.prop('disabled', !canComplete);

        if (canComplete) {
            helper.text('Denzy has marked this Engage discussion as ready for completion.');
            badge.text('Ready to complete').removeClass('bg-secondary bg-warning').addClass('bg-success');
            return;
        }

        helper.text('Continue the Engage discussion until Denzy marks it ready for completion.');
        badge.text('Discussion in progress').removeClass('bg-success bg-warning').addClass('bg-secondary');
    }

    function appendEngageStudentMessage(text) {
        $('#engage-chat-placeholder').remove();
        $('#engage-chat-messages').append(
            '<div class="text-end mb-2"><span class="bg-primary text-white p-2 rounded d-inline-block">' +
                escapeHtml(text).replace(/\n/g, '<br>') +
            '</span></div>'
        );
    }

    function appendEngageAssistantMessage(text, response) {
        var extra = '';

        if (response && response.classification) {
            var confidence = response.confidence ? ' (' + Math.round(parseFloat(response.confidence) * 100) + '%)' : '';
            extra += '<div class="small mt-1 text-muted">Class: ' + escapeHtml(response.classification.replace(/_/g, ' ')) + confidence + '</div>';
        }

        if (response && response.engage_status) {
            extra += '<div class="small text-muted">Status: ' + escapeHtml(response.engage_status.replace(/_/g, ' ')) + '</div>';
            updateEngageCompletionState(response.engage_status === 'complete');
        }

        $('#engage-chat-placeholder').remove();
        $('#engage-chat-messages').append(
            '<div class="text-start mb-2"><span class="bg-white border p-2 rounded d-inline-block">' +
                escapeHtml(text).replace(/\n/g, '<br>') + extra +
            '</span></div>'
        );
    }

    function showEngageAssistantLoading() {
        removeEngageAssistantLoading();
        $('#engage-chat-placeholder').remove();
        $('#engage-chat-messages').append(
            '<div class="text-start mb-2" id="engage-assistant-loading"><span class="bg-white border p-2 rounded d-inline-block">' +
                '<span class="typing-loader-label">Denzy is typing</span>' +
                '<span class="typing-loader-dots"><span>.</span><span>.</span><span>.</span></span>' +
            '</span></div>'
        );
        scrollToBottom('#engage-chat-messages');
    }

    function removeEngageAssistantLoading() {
        $('#engage-assistant-loading').remove();
    }

    function appendFloatingAssistantMessage(text, response) {
        var extra = '';

        if (response && response.stage === 'engage' && response.classification) {
            var confidence = response.confidence ? ' (' + Math.round(parseFloat(response.confidence) * 100) + '%)' : '';
            extra += '<div class="small mt-1 text-muted">Class: ' + response.classification + confidence + '</div>';
        }

        if (response && response.stage === 'engage' && response.engage_status) {
            extra += '<div class="small text-muted">Status: ' + response.engage_status + '</div>';
        }

        $('#chat-messages').append('<div class="text-start mb-2"><span class="bg-light p-2 rounded d-inline-block">' + text + extra + '</span></div>');
    }

    function showFloatingAssistantLoading() {
        removeFloatingAssistantLoading();
        $('#chat-messages').append(
            '<div class="text-start mb-2" id="floating-assistant-loading"><span class="bg-light p-2 rounded d-inline-block">' +
                '<span class="typing-loader-label">Denzy is typing</span>' +
                '<span class="typing-loader-dots"><span>.</span><span>.</span><span>.</span></span>' +
            '</span></div>'
        );
        scrollToBottom('#chat-messages');
    }

    function removeFloatingAssistantLoading() {
        $('#floating-assistant-loading').remove();
    }

    function requestEngageStart() {
        if (engageMode !== 'chat' || engageStarted) {
            return;
        }

        engageStarted = true;
        showEngageAssistantLoading();

        $.ajax({
            url: '{{ route("student.lessons.ai.ask", $lesson) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                question: '__engage_start__',
                stage: 'engage',
                intent: 'start'
            },
            success: function(response) {
                removeEngageAssistantLoading();
                appendEngageAssistantMessage(response.answer, response);
                scrollToBottom('#engage-chat-messages');
            },
            error: function(xhr) {
                removeEngageAssistantLoading();
                var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to start engage flow';
                $('#engage-chat-placeholder').remove();
                $('#engage-chat-messages').append('<div class="text-start mb-2 text-danger">Error: ' + escapeHtml(message) + '</div>');
                scrollToBottom('#engage-chat-messages');
            }
        });
    }
   
    // Mark stage as complete
    $('.mark-complete').on('click', function() {
        var btn = $(this);
        var stage = btn.data('stage');
        $.ajax({
            url: '{{ route("student.lessons.stages.complete", ["lesson" => $lesson->id, "stage" => "_stage_"]) }}'.replace('_stage_', stage),
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                btn.replaceWith('<span class="badge bg-success">Completed <i class="fas fa-check"></i></span>');
                if (stage === 'engage') {
                    $('#engage-chat-form :input').prop('disabled', true);
                }
                // Update tab style
                $('#' + stage + '-tab').addClass('bg-success text-white');
                // Optionally show success message
                alert('Stage marked as complete!');
            },
            error: function(xhr) {
                console.error(xhr);
                alert('Error: ' + xhr.responseJSON.error);
            }
        });
    });

    // Quiz submission
    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
            url: '{{ route("student.lessons.quiz.submit", $lesson) }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                $('#quiz-result').html('<div class="alert alert-success">You scored ' + response.score + '/' + response.total + ' (' + response.percentage + '%)</div>');
                // Mark evaluate as complete on page reload? Or we can update UI
                // For simplicity, reload page to show updated progress
                setTimeout(function() {
                    location.reload();
                }, 2000);
            },
            error: function(xhr) {
                alert('Error submitting quiz.');
            }
        });
    });

    // AI Chat
    $('#ai-chat-button').on('click', function() {
       // console.log('clickingS');
        var container = $('#ai-chat-container');
        container.toggle('slow');

    });
    $('#ai-chat-close').on('click', function() {
        $('#ai-chat-container').hide('slow');
    });

    $('#engage-chat-form').on('submit', function(e) {
        e.preventDefault();

        var question = $('#engage-chat-input').val();
        if (!question.trim()) {
            return;
        }

        appendEngageStudentMessage(question);
        $('#engage-chat-input').val('');
        showEngageAssistantLoading();

        $.ajax({
            url: '{{ route("student.lessons.ai.ask", $lesson) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                question: question,
                stage: 'engage',
                intent: 'answer'
            },
            success: function(response) {
                engageStarted = true;
                removeEngageAssistantLoading();
                appendEngageAssistantMessage(response.answer, response);
                scrollToBottom('#engage-chat-messages');
            },
            error: function(xhr) {
                removeEngageAssistantLoading();
                var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unknown error';
                $('#engage-chat-messages').append('<div class="text-start mb-2 text-danger">Error: ' + escapeHtml(message) + '</div>');
                scrollToBottom('#engage-chat-messages');
            }
        });
    });

    $('#engage-mcq-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var selectedOption = form.find('input[name="selected_option"]:checked').val();

        if (!selectedOption) {
            alert('Select one option before submitting.');
            return;
        }

        form.find(':input').prop('disabled', true);

        $.ajax({
            url: '{{ route("student.lessons.engage-mcq.submit", $lesson) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                selected_option: selectedOption
            },
            success: function(response) {
                $('#engage-status-badge').text('Complete').removeClass('bg-secondary').addClass('bg-success');
                $('#engage-mcq-result').html(
                    '<div class="alert ' + (response.is_correct ? 'alert-success' : 'alert-warning') + ' mb-0">' +
                        '<div><strong>Your choice:</strong> ' + escapeHtml(String(response.selected_option).toUpperCase()) + '</div>' +
                        '<div class="mt-2">' + escapeHtml(response.feedback || '').replace(/\n/g, '<br>') + '</div>' +
                    '</div>'
                );
                form.remove();
                $('#engage-complete-helper').text('Engage checkpoint completed.');
                $('#engage-complete-button').replaceWith('<span class="badge bg-success">Completed <i class="fas fa-check"></i></span>');
                $('#engage-tab').addClass('bg-success text-white');
            },
            error: function(xhr) {
                form.find(':input').prop('disabled', false);
                var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to submit checkpoint';
                alert(message);
            }
        });
    });

    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        var question = $('#chat-input').val();
        if (!question.trim()) return;
        var stage = activeStage(); // get current stage from active tab
        var intent = stage === 'engage' ? 'answer' : 'ask';

        var messagesDiv = $('#chat-messages');
        messagesDiv.append('<div class="text-end mb-2"><span class="bg-primary text-white p-2 rounded">' + question + '</span></div>');
        $('#chat-input').val('');
        showFloatingAssistantLoading();

        $.ajax({
            url: '{{ route("student.lessons.ai.ask", $lesson) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                question: question,
                stage: stage,
                intent: intent
            },
            success: function(response) {
                removeFloatingAssistantLoading();
                appendFloatingAssistantMessage(response.answer, response);
                messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
            },
            error: function(xhr) {
                removeFloatingAssistantLoading();
                messagesDiv.append('<div class="text-start mb-2 text-danger">Error: ' + (xhr.responseJSON.message || 'Unknown error') + '</div>');
            }
        });
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        updateDenzyVisibility();

        if (engageMode === 'chat' && activeStage() === 'engage' && !engageStarted) {
            requestEngageStart();
        }
    });

    updateDenzyVisibility();
    updateEngageCompletionState({{ $canMarkEngageComplete ? 'true' : 'false' }});
    scrollToBottom('#engage-chat-messages');

    if (engageMode === 'chat' && activeStage() === 'engage' && !engageStarted) {
        requestEngageStart();
    }
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    .typing-loader-label {
        font-weight: 500;
    }

    .typing-loader-dots {
        display: inline-block;
        min-width: 18px;
    }

    .typing-loader-dots span {
        opacity: 0.25;
        animation: typingDots 1.2s infinite;
    }

    .typing-loader-dots span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-loader-dots span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typingDots {
        0%,
        80%,
        100% {
            opacity: 0.25;
        }

        40% {
            opacity: 1;
        }
    }
</style>
@endpush