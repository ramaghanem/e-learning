@php
    $course = $course ?? null;
    $difficulty = old('difficulty', $course?->difficulty);
@endphp

<x-ui.form-input
    name="title"
    label="Course title"
    :value="old('title', $course?->title)"
    required
    maxlength="200"
/>

<x-ui.form-input
    name="short_description"
    label="Short description"
    :value="old('short_description', $course?->short_description)"
    required
    maxlength="200"
    help="Shown on course cards. Up to 200 characters."
/>

{{-- Plain textarea for now. A rich text editor can be attached to #description later. --}}
<div class="mb-3">
    <label for="description" class="form-label">
        Full description
    </label>

    <textarea
        id="description"
        name="description"
        rows="8"
        required
        class="form-control @error('description') is-invalid @enderror"
    >{{ old('description', $course?->description) }}</textarea>

    @error('description')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="category_id" class="form-label">
                Category
            </label>

            <select
                id="category_id"
                name="category_id"
                class="form-select @error('category_id') is-invalid @enderror"
                required
            >
                <option value="">Select a category</option>

                @foreach ($categories as $category)
                    <option
                        value="{{ $category->id }}"
                        @selected(old('category_id', $course?->category_id) == $category->id)
                    >
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>

            @error('category_id')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>



@if (($area ?? null) === 'admin')
    <div class="mb-3">
        <label for="instructor_id" class="form-label">
            Instructor
        </label>

        <select
            name="instructor_id"
            id="instructor_id"
            class="form-select @error('instructor_id') is-invalid @enderror"
            required
        >
            <option value="">Choose an instructor</option>

            @foreach ($instructors as $instructor)
                <option
                    value="{{ $instructor->id }}"
                    @selected(
                        old(
                            'instructor_id',
                            $course->instructor_id ?? ''
                        ) == $instructor->id
                    )
                >
                    {{ $instructor->name }} ({{ $instructor->email }})
                </option>
            @endforeach
        </select>

        @error('instructor_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

    </div>

    <div class="col-md-6">
        <x-ui.form-input
            name="language"
            label="Language"
            :value="old('language', $course?->language ?? 'English')"
        />
    </div>
</div>

<div class="mb-3">
    <span class="form-label d-block" id="difficultyLabel">
        Difficulty level
    </span>

    <div role="radiogroup" aria-labelledby="difficultyLabel">
        @foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)
            <div class="form-check form-check-inline">
                <input
                    type="radio"
                    id="difficulty_{{ $value }}"
                    name="difficulty"
                    value="{{ $value }}"
                    class="form-check-input @error('difficulty') is-invalid @enderror"
                    required
                    @checked($difficulty === $value)
                >

                <label for="difficulty_{{ $value }}" class="form-check-label">
                    {{ $label }}
                </label>
            </div>
        @endforeach
    </div>

    @error('difficulty')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
    @enderror
</div>

<div class="picture-field mb-4">
    <div id="thumbnailPreviewWrap" style="width: 14rem;">
        @include('partials.courses.thumbnail', ['course' => $course, 'class' => 'rounded'])
    </div>

    <div class="flex-grow-1">
        <label for="thumbnail" class="form-label">
            Course thumbnail
        </label>

        <input
            type="file"
            id="thumbnail"
            name="thumbnail"
            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
            class="form-control @error('thumbnail') is-invalid @enderror"
        >

        @error('thumbnail')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @else
            <div class="form-text">
                JPG, JPEG or PNG. Maximum file size: 2MB. Recommended size: 1280 × 720 pixels.
                @if ($course?->thumbnail)
                    Leave empty to keep the current thumbnail.
                @endif
            </div>
        @enderror
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('thumbnail').addEventListener('change', function () {
        const file = this.files[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const img = document.createElement('img');

        img.src = URL.createObjectURL(file);
        img.alt = 'New thumbnail preview';
        img.className = 'course-card-thumb rounded';

        document.getElementById('thumbnailPreviewWrap').replaceChildren(img);
    });
</script>
@endpush
