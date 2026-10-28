@extends('layouts.main')

@section('title', 'Categories')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">Categories</h1>
            </div>

            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>
                Add New Category
            </a>
        </div>

        @if ($categories->isEmpty())

            <x-ui.empty-state
                icon="bi-tags"
                title="No categories yet"
            >
                Add your first category to start organizing courses.
            </x-ui.empty-state>

        @else

            <div class="panel p-0 overflow-hidden">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0 data-table">

                        <thead>
                            <tr>
                                <th>Category name</th>
                                <th>Description</th>
                                <th class="text-end">Number of courses</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($categories as $category)

                                <tr>

                                    <td class="fw-semibold">
                                        {{ $category->name }}
                                    </td>

                                    <td class="text-body-secondary">
                                        {{ $category->description ?? '—' }}
                                    </td>

                                    <td class="text-end">
                                        {{ $category->courses_count ?? 0 }}
                                    </td>

                                    <td class="text-end text-nowrap">

                                        <a
                                            href="{{ route('admin.categories.edit', $category) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteCategoryModal-{{ $category->id }}"
                                        >
                                            Delete
                                        </button>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

            @foreach ($categories as $category)

                <div
                    class="modal fade"
                    id="deleteCategoryModal-{{ $category->id }}"
                    tabindex="-1"
                    aria-labelledby="deleteCategoryModalLabel-{{ $category->id }}"
                    aria-hidden="true"
                >
                    <div class="modal-dialog modal-dialog-centered">

                        <div class="modal-content">

                            <div class="modal-header">
                                <h2
                                    class="modal-title fs-5"
                                    id="deleteCategoryModalLabel-{{ $category->id }}"
                                >
                                    Delete Category
                                </h2>

                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"
                                ></button>
                            </div>

                            <div class="modal-body">
                                Are you sure? This action cannot be undone.
                            </div>

                            <div class="modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    data-bs-dismiss="modal"
                                >
                                    Cancel
                                </button>

                                <form
                                    method="POST"
                                    action="{{ route('admin.categories.destroy', $category) }}"
                                    class="m-0"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-danger"
                                    >
                                        Delete
                                    </button>
                                </form>

                            </div>

                        </div>

                    </div>
                </div>

            @endforeach

        @endif

    </div>
</section>
@endsection
