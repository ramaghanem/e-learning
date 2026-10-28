@extends('layouts.main')

@section('title', 'Users')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">Users</h1>
            </div>

            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>
                Create User
            </a>
        </div>


        <div class="panel mb-4">

            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">

                <div class="col-md-6">
                    <label for="search" class="form-label">
                        Search
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="Search by name or email"
                    >
                </div>

                <div class="col-md-4">
                    <label for="role" class="form-label">
                        Role
                    </label>

                    <select id="role" name="role" class="form-select">
                        <option value="">All</option>
                        <option value="admin" @selected(request('role') === 'admin')>
                            Admin
                        </option>
                        <option value="instructor" @selected(request('role') === 'instructor')>
                            Instructor
                        </option>
                        <option value="student" @selected(request('role') === 'student')>
                            Student
                        </option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>
                        Search
                    </button>
                </div>

            </form>

        </div>

        @if ($users->isEmpty())

            <x-ui.empty-state
                icon="bi-people"
                title="No users found"
            />

        @else

            <div class="panel p-0 overflow-hidden">

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0 data-table">

                        <thead>
                            <tr>
                                <th>Profile picture</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registration date</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($users as $user)

                                <tr>

                                    <td>
                                        <x-ui.avatar :user="$user" size="sm" />
                                    </td>

                                    <td class="fw-semibold">
                                        {{ $user->name }}
                                    </td>

                                    <td>
                                        {{ $user->email }}
                                    </td>

                                    <td>
                                        <span class="role-badge role-{{ $user->role }}">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>

                                    <td class="small text-body-secondary text-nowrap">
                                        {{ $user->created_at?->format('M j, Y') }}
                                    </td>

                                    <td>
                                        @if ($user->status === 'active')
                                            <span class="badge text-bg-success">
                                                Active
                                            </span>
                                        @else
                                            <span class="badge text-bg-secondary">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end text-nowrap">

                                        <a
                                            href="{{ route('admin.users.show', $user) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="{{ route('admin.users.edit', $user) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUserModal-{{ $user->id }}"
                                        >
                                            Delete
                                        </button>

                                    </td>

                                </tr>

                                <div
                                    class="modal fade"
                                    id="deleteUserModal-{{ $user->id }}"
                                    tabindex="-1"
                                    aria-labelledby="deleteUserModalLabel-{{ $user->id }}"
                                    aria-hidden="true"
                                >
                                    <div class="modal-dialog modal-dialog-centered">

                                        <div class="modal-content">

                                            <div class="modal-header">
                                                <h2
                                                    class="modal-title fs-5"
                                                    id="deleteUserModalLabel-{{ $user->id }}"
                                                >
                                                    Delete User
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
                                                    action="{{ route('admin.users.destroy', $user) }}"
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

                        </tbody>

                    </table>

                </div>

            </div>

            <div class="mt-4">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>

        @endif

    </div>
</section>
@endsection
