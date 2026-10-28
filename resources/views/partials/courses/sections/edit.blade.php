
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Edit Section</h1>
                <p class="text-body-secondary">
                    Update the section details.
                </p>
            </div>

            <a href="{{ route($area . '.courses.content', $course) }}"
               class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Course Content
            </a>
        </div>

        <div class="panel">
            <form method="POST"
                  action="{{ route($area . '.courses.sections.update', [$course, $section]) }}">

                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="title" class="form-label">
                        Section Title
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title', $section->title) }}"
                        class="form-control @error('title') is-invalid @enderror"
                        maxlength="200"
                        required
                    >

                    @error('title')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="form-control @error('description') is-invalid @enderror"
                    >{{ old('description', $section->description) }}</textarea>

                    @error('description')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>
                    Save Changes
                </button>

            </form>
        </div>

    </div>
</section>
