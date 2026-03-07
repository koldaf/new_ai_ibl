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

            <!-- Mark as Complete Button (except evaluate if not done yet) -->
            @if(!$progress->{$stage.'_completed'} && $stage != 'evaluate')
                <button class="btn btn-success mark-complete" data-stage="{{ $stage }}">Mark {{ ucfirst($stage) }} as Complete</button>
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
<div style="position: fixed; top: 80%; left: 90%; z-index: 1000;">
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
                // Update tab style
                $('#' + stage + '-tab').addClass('bg-success text-white');
                // Optionally show success message
                alert('Stage marked as complete!');
            },
            error: function(xhr) {
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

    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        var question = $('#chat-input').val();
        if (!question.trim()) return;
        var stage = $('.nav-link.active').data('bs-target').substring(1); // get current stage from active tab

        var messagesDiv = $('#chat-messages');
        messagesDiv.append('<div class="text-end mb-2"><span class="bg-primary text-white p-2 rounded">' + question + '</span></div>');
        $('#chat-input').val('');

        $.ajax({
            url: '{{ route("student.lessons.ai.ask", $lesson) }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                question: question,
                stage: stage
            },
            success: function(response) {
                messagesDiv.append('<div class="text-start mb-2"><span class="bg-light p-2 rounded">' + response.answer + '</span></div>');
                messagesDiv.scrollTop(messagesDiv[0].scrollHeight);
            },
            error: function(xhr) {
                messagesDiv.append('<div class="text-start mb-2 text-danger">Error: ' + (xhr.responseJSON.message || 'Unknown error') + '</div>');
            }
        });
    });
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
@endpush