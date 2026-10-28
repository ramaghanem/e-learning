@php
    $status = old('status', $course->status);
@endphp

<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">{{ ucfirst($area) }}</span>
                <h1 class="page-title">Edit Course</h1>
            </div>

            <a
                href="{{ route($area . '.courses.index') }}"
                class="btn btn-outline-primary"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Back to Courses
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <form
                    method="POST"
                    action="{{ route($area . '.courses.update', $course) }}"
                    enctype="multipart/form-data"
                    novalidate
                >

                    @csrf
                    @method('PATCH')

                    <div class="panel mb-4">
                        <h2 class="panel-title">Basic information</h2>

                        @include('partials.courses.fields-basic')

                        <div class="row">
                            <div class="col-md-6">
                                <label for="status" class="form-label">
                                    Status
                                </label>

                                <select
                                    id="status"
                                    name="status"
                                    class="form-select @error('status') is-invalid @enderror"
                                    required
                                >
                                    <option
                                        value="draft"
                                        @selected($status === 'draft')
                                    >
                                        Draft
                                    </option>

                                    <option
                                        value="published"
                                        @selected($status === 'published')
                                    >
                                        Published
                                    </option>
                                </select>

                                @error('status')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="panel mb-4">
                        <h2 class="panel-title">Course details</h2>

                        @include('partials.courses.fields-details')
                    </div>

                    <button type="submit" class="btn btn-primary px-4">
                        Save Changes
                    </button>

                </form>

            </div>
        </div>
    </div>
</section>
