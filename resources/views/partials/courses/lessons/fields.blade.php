@php
    $lesson = $lesson ?? null;
    $type = old('type', $lesson?->type ?? 'video');

    // An unchecked checkbox is not sent, so after a failed submit use only the old input.
    $isFreePreview = session()->hasOldInput()
        ? old('is_free_preview')
        : $lesson?->is_free_preview;
@endphp

<x-ui.form-input
    name="title"
    label="Lesson title"
    :value="old('title', $lesson?->title)"
    required
    maxlength="200"
/>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="section_id" class="form-label">
                Section
            </label>

            <select
                id="section_id"
                name="section_id"
                class="form-select @error('section_id') is-invalid @enderror"
                required
            >
                <option value="">Select a section</option>

                @foreach ($sections as $section)
                    <option
                        value="{{ $section->id }}"
                        @selected(old('section_id', $lesson?->section_id) == $section->id)
                    >
                        {{ $section->title }}
                    </option>
                @endforeach
            </select>

            @error('section_id')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="mb-3">
            <label for="type" class="form-label">
                Lesson type
            </label>

            <select
                id="type"
                name="type"
                class="form-select @error('type') is-invalid @enderror"
                required
            >
                <option value="video" @selected($type === 'video')>
                    Video
                </option>

                <option value="article" @selected($type === 'article')>
                    Article
                </option>

                <option value="quiz" @selected($type === 'quiz')>
                    Quiz
                </option>
            </select>

            @error('type')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
</div>

{{-- Shown only when the lesson type is "video" --}}
<div data-lesson-type="video" @class(['d-none' => $type !== 'video'])>

    @php
        $videoSource = old('video_source', match (true) {
            ! $lesson => 'upload',
            filled($lesson->video_url) => 'url',
            default => 'upload',
        });
    @endphp

    <div class="mb-3">
        <span class="form-label d-block" id="videoSourceLabel">
            Video source
        </span>

        <div role="radiogroup" aria-labelledby="videoSourceLabel">
            @foreach (['upload' => 'Upload a video file', 'url' => 'YouTube or Vimeo link'] as $value => $label)
                <div class="form-check form-check-inline">
                    <input
                        type="radio"
                        id="video_source_{{ $value }}"
                        name="video_source"
                        value="{{ $value }}"
                        class="form-check-input @error('video_source') is-invalid @enderror"
                        @checked($videoSource === $value)
                    >

                    <label for="video_source_{{ $value }}" class="form-check-label">
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>

        @error('video_source')
            <div class="invalid-feedback d-block">
                {{ $message }}
            </div>
        @enderror

    </div>

    <div data-video-source="url" @class(['d-none' => $videoSource === 'upload'])>
        <x-ui.form-input
            name="video_url"
            label="Video link"
            type="url"
            :value="old('video_url', $lesson?->video_url)"
            placeholder="https://www.youtube.com/watch?v=..."
            help="Paste a YouTube or Vimeo link."
        />
    </div>

    <div data-video-source="upload" @class(['mb-3', 'd-none' => $videoSource === 'url'])>
        <label for="video_file" class="form-label">
            Video file
        </label>

        @if ($lesson?->video_path)
            @php
                $removeVideo = (bool) old('remove_video_file');
            @endphp

            <div class="d-flex flex-wrap align-items-center gap-2 small mb-2" data-removable>
                {{-- Ticked by the Remove button. The file is only deleted when the form is saved. --}}
                <input
                    type="checkbox"
                    name="remove_video_file"
                    value="1"
                    class="d-none"
                    data-remove-input
                    @checked($removeVideo)
                >

                <span data-remove-target @class(['text-decoration-line-through opacity-50' => $removeVideo])>
                    <i class="bi bi-file-earmark-play me-1"></i>
                    Current video:
                    <a href="{{ asset('storage/' . $lesson->video_path) }}" target="_blank" rel="noopener">
                        {{ basename($lesson->video_path) }}
                    </a>
                    <span class="text-body-secondary">(uploading a new video replaces it)</span>
                </span>

                <button
                    type="button"
                    @class(['btn btn-sm btn-outline-danger', 'd-none' => $removeVideo])
                    aria-label="Remove the current video"
                    data-remove-button
                >
                    <i class="bi bi-trash me-1"></i>
                    Remove
                </button>

                <span @class(['d-inline-flex align-items-center gap-2', 'd-none' => ! $removeVideo]) data-remove-pending>
                    <span class="text-danger fw-semibold" role="status">Will be removed</span>

                    <button type="button" class="btn btn-sm btn-outline-secondary" data-undo-button>
                        Undo
                    </button>
                </span>
            </div>
        @endif

        <input
            type="file"
            id="video_file"
            name="video_file"
            accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime"
            class="form-control @error('video_file') is-invalid @enderror"
        >

        @error('video_file')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @else
            <div class="form-text">
                MP4, WebM or MOV, up to 500MB.
            </div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label for="video_thumbnail_file" class="form-label">
                    Video thumbnail
                </label>

                @if ($lesson?->video_thumbnail)
                    @php
                        $removeThumbnail = (bool) old('remove_video_thumbnail');
                    @endphp

                    <div class="mb-2" data-removable>
                        {{-- Ticked by the Remove button. The file is only deleted when the form is saved. --}}
                        <input
                            type="checkbox"
                            name="remove_video_thumbnail"
                            value="1"
                            class="d-none"
                            data-remove-input
                            @checked($removeThumbnail)
                        >

                        <img
                            src="{{ asset('storage/' . $lesson->video_thumbnail) }}"
                            alt="Current video thumbnail"
                            @class(['rounded d-block mb-1', 'opacity-50' => $removeThumbnail])
                            style="width: 10rem;"
                            data-remove-target
                        >

                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span
                                @class(['text-body-secondary', 'text-decoration-line-through opacity-50' => $removeThumbnail])
                                data-remove-target
                            >
                                Current thumbnail (uploading a new image replaces it)
                            </span>

                            <button
                                type="button"
                                @class(['btn btn-sm btn-outline-danger', 'd-none' => $removeThumbnail])
                                aria-label="Remove the current thumbnail"
                                data-remove-button
                            >
                                <i class="bi bi-trash me-1"></i>
                                Remove
                            </button>

                            <span @class(['d-inline-flex align-items-center gap-2', 'd-none' => ! $removeThumbnail]) data-remove-pending>
                                <span class="text-danger fw-semibold" role="status">Will be removed</span>

                                <button type="button" class="btn btn-sm btn-outline-secondary" data-undo-button>
                                    Undo
                                </button>
                            </span>
                        </div>
                    </div>
                @endif

                <input
                    type="file"
                    id="video_thumbnail_file"
                    name="video_thumbnail_file"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                    class="form-control @error('video_thumbnail_file') is-invalid @enderror"
                >

                @error('video_thumbnail_file')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @else
                    <div class="form-text">
                        JPG, JPEG or PNG, up to 2MB.
                    </div>
                @enderror
            </div>
        </div>

        <div class="col-md-6">
            <x-ui.form-input
                name="duration_minutes"
                label="Duration (minutes)"
                type="number"
                :value="old('duration_minutes', $lesson?->duration_minutes)"
                min="0"
                step="1"
            />
        </div>
    </div>

</div>

{{-- Shown only when the lesson type is "article" --}}
<div data-lesson-type="article" @class(['d-none' => $type !== 'article'])>

    <div class="mb-3">
        <label for="content" class="form-label">
            Article content
        </label>

        <textarea
            id="content"
            name="content"
            rows="8"
            class="form-control @error('content') is-invalid @enderror"
        >{{ old('content', $lesson?->content) }}</textarea>

        @error('content')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-ui.form-input
                name="reading_time_minutes"
                label="Estimated reading time (minutes)"
                type="number"
                :value="old('reading_time_minutes', $lesson?->reading_time_minutes)"
                min="0"
                step="1"
            />
        </div>
    </div>

</div>

{{-- Shown only when the lesson type is "quiz" --}}
@php
    $isQuiz = $lesson?->type === 'quiz';

    // An unchecked checkbox is not sent, so after a failed submit use only the old input.
    $showReview = session()->hasOldInput()
        ? old('quiz_show_review')
        : ($isQuiz ? $lesson->quiz_show_review : true);
@endphp

<div data-lesson-type="quiz" @class(['d-none' => $type !== 'quiz'])>

    <div class="mb-3">
        <label for="quiz_instructions" class="form-label">
            Quiz instructions
        </label>

        
        <textarea
            id="quiz_instructions"
            name="quiz_instructions"
            rows="4"
            maxlength="5000"
            class="form-control @error('quiz_instructions') is-invalid @enderror"
        >{{ old('quiz_instructions', $isQuiz ? $lesson->content : null) }}</textarea>

        @error('quiz_instructions')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @else
            <div class="form-text">
                Optional. Shown to students before they start the quiz.
            </div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-4">
            <x-ui.form-input
                name="quiz_passing_score"
                label="Passing score (%)"
                type="number"
                :value="old('quiz_passing_score', $isQuiz ? $lesson->quiz_passing_score : 70)"
                min="1"
                max="100"
                step="1"
                required
            />
        </div>

        <div class="col-md-4">
            <x-ui.form-input
                name="quiz_time_limit_minutes"
                label="Time limit (minutes)"
                type="number"
                :value="old('quiz_time_limit_minutes', $isQuiz ? $lesson->quiz_time_limit_minutes : null)"
                min="1"
                max="600"
                step="1"
                help="Leave empty for no time limit."
            />
        </div>

        <div class="col-md-4">
            <x-ui.form-input
                name="quiz_max_attempts"
                label="Attempts allowed"
                type="number"
                :value="old('quiz_max_attempts', $isQuiz ? $lesson->quiz_max_attempts : null)"
                min="1"
                max="100"
                step="1"
                help="Leave empty for unlimited attempts."
            />
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check">
            <input
                type="checkbox"
                id="quiz_show_review"
                name="quiz_show_review"
                value="1"
                class="form-check-input @error('quiz_show_review') is-invalid @enderror"
                @checked($showReview)
            >

            <label for="quiz_show_review" class="form-check-label">
                Let students review their answers
            </label>

            @error('quiz_show_review')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="form-text">
            After submitting, students see each question with their answer and the correct one.
            When off, they only see their score.
        </div>
    </div>

</div>

<div class="mb-3">
    <label for="resources" class="form-label">
        Downloadable resources
    </label>

    @if ($lesson && $lesson->resources->isNotEmpty())
        <div class="small mb-2">
            <span class="fw-semibold">Current resources</span>

            <ul class="list-unstyled mt-1 mb-1">
                @foreach ($lesson->resources as $resource)
                    @php
                        $removeResource = in_array($resource->id, (array) old('remove_resources', []));
                    @endphp

                    <li class="d-flex flex-wrap align-items-center gap-2 mb-1" data-removable>
                        {{-- Ticked by the Remove button. The file is only deleted when the form is saved. --}}
                        <input
                            type="checkbox"
                            name="remove_resources[]"
                            value="{{ $resource->id }}"
                            class="d-none"
                            data-remove-input
                            @checked($removeResource)
                        >

                        <span data-remove-target @class(['text-decoration-line-through opacity-50' => $removeResource])>
                            <i class="bi bi-paperclip me-1"></i>
                            <a href="{{ asset('storage/' . $resource->file_path) }}" target="_blank" rel="noopener">
                                {{ $resource->original_name }}
                            </a>
                            <span class="text-body-secondary">
                                @if ($resource->file_size >= 1048576)
                                    ({{ number_format($resource->file_size / 1048576, 1) }} MB)
                                @else
                                    ({{ number_format($resource->file_size / 1024, 1) }} KB)
                                @endif
                            </span>
                        </span>

                        <button
                            type="button"
                            @class(['btn btn-sm btn-outline-danger', 'd-none' => $removeResource])
                            aria-label="Remove {{ $resource->original_name }}"
                            data-remove-button
                        >
                            <i class="bi bi-trash me-1"></i>
                            Remove
                        </button>

                        <span @class(['d-inline-flex align-items-center gap-2', 'd-none' => ! $removeResource]) data-remove-pending>
                            <span class="text-danger fw-semibold" role="status">Will be removed</span>

                            <button type="button" class="btn btn-sm btn-outline-secondary" data-undo-button>
                                Undo
                            </button>
                        </span>
                    </li>
                @endforeach
            </ul>

            <span class="text-body-secondary">
                Removed files are deleted when you click Save Changes. New files you upload are added to these resources.
            </span>
        </div>
    @endif

    <input
        type="file"
        id="resources"
        name="resources[]"
        multiple
        accept=".pdf,.zip,.doc,.docx,.ppt,.pptx"
        class="form-control @if ($errors->has('resources') || $errors->has('resources.*')) is-invalid @endif"
    >

    @if ($errors->has('resources') || $errors->has('resources.*'))
        <div class="invalid-feedback">
            {{ $errors->first('resources') ?: $errors->first('resources.*') }}
        </div>
    @elseif ($errors->has('remove_resources') || $errors->has('remove_resources.*'))
        <div class="invalid-feedback d-block">
            {{ $errors->first('remove_resources') ?: $errors->first('remove_resources.*') }}
        </div>
    @else
        <div class="form-text">
            Optional files students can download with this lesson.
            PDF, ZIP, DOC, DOCX, PPT or PPTX. Up to 10 files, 20MB each.
        </div>
    @endif
</div>

<div class="mb-4">
    <div class="form-check">
        <input
            type="checkbox"
            id="is_free_preview"
            name="is_free_preview"
            value="1"
            class="form-check-input @error('is_free_preview') is-invalid @enderror"
            @checked($isFreePreview)
        >

        <label for="is_free_preview" class="form-check-label">
            Free preview
        </label>

        @error('is_free_preview')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="form-text">
        Students can open a free preview lesson without enrolling in the course.
    </div>
</div>

@push('scripts')
<script>
    const lessonType = document.getElementById('type');
    const lessonTypeFields = document.querySelectorAll('[data-lesson-type]');

    // Show only the fields for the selected lesson type.
    // Hidden fields are not disabled, so the form still submits normally.
    const showLessonTypeFields = () => {
        lessonTypeFields.forEach((group) => {
            group.classList.toggle('d-none', group.dataset.lessonType !== lessonType.value);
        });
    };

    lessonType.addEventListener('change', showLessonTypeFields);
    showLessonTypeFields();

    // Show only the field for the chosen video source (file or link).
    // While nothing is chosen yet, both stay visible.
    const videoSourceInputs = document.querySelectorAll('input[name="video_source"]');
    const videoSourceFields = document.querySelectorAll('[data-video-source]');

    const showVideoSourceFields = () => {
        const chosen = document.querySelector('input[name="video_source"]:checked');

        videoSourceFields.forEach((field) => {
            field.classList.toggle('d-none', chosen !== null && field.dataset.videoSource !== chosen.value);
        });
    };

    videoSourceInputs.forEach((input) => input.addEventListener('change', showVideoSourceFields));
    showVideoSourceFields();
</script>
@endpush

{{-- Edit Lesson only: Remove / Undo buttons for the current files. --}}
@if ($lesson)
    @push('scripts')
    <script>
        // Remove only marks the file (it ticks the hidden checkbox).
        // Nothing is deleted until the form is saved.
        document.querySelectorAll('[data-removable]').forEach((item) => {
            const input = item.querySelector('[data-remove-input]');
            const removeButton = item.querySelector('[data-remove-button]');
            const undoButton = item.querySelector('[data-undo-button]');
            const pending = item.querySelector('[data-remove-pending]');

            const showState = () => {
                item.querySelectorAll('[data-remove-target]').forEach((target) => {
                    target.classList.toggle('opacity-50', input.checked);

                    if (target.tagName !== 'IMG') {
                        target.classList.toggle('text-decoration-line-through', input.checked);
                    }
                });

                removeButton.classList.toggle('d-none', input.checked);
                pending.classList.toggle('d-none', ! input.checked);
            };

            removeButton.addEventListener('click', () => {
                input.checked = true;
                showState();
                undoButton.focus();
            });

            undoButton.addEventListener('click', () => {
                input.checked = false;
                showState();
                removeButton.focus();
            });

            // Also covers a checkbox the browser restored, e.g. after pressing Back.
            showState();
        });
    </script>
    @endpush
@endif
