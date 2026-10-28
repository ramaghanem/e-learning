@extends('layouts.main')

@section('title', 'Edit Category')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">Edit Category</h1>
            </div>

            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Categories
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <div class="panel">
                    <h2 class="panel-title">Category information</h2>

                    <form method="POST"
                          action="{{ route('admin.categories.update', $category) }}"
                          novalidate>

                        @csrf
                        @method('PATCH')

                        <x-ui.form-input
                            name="name"
                            label="Name"
                            :value="old('name', $category->name)"
                            required
                        />

                        <div class="mb-4">
                            <label for="description" class="form-label">
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="4"
                                class="form-control @error('description') is-invalid @enderror"
                            >{{ old('description', $category->description) }}</textarea>

                            @error('description')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            Save Changes
                        </button>

                    </form>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
