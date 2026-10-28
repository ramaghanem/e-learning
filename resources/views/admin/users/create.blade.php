@extends('layouts.main')

@section('title', 'Create User')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">Create New User</h1>
            </div>

            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Users List
            </a>
        </div>

        <div class="row g-4">

            <div class="col-lg-9">

                <div class="panel">
                    <h2 class="panel-title">User information</h2>

                    @if (session('success'))
                        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('admin.users.store') }}"
                          novalidate>

                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="name"
                                    label="Name"
                                    :value="old('name')"
                                    required
                                    autocomplete="name"
                                />
                            </div>

                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="email"
                                    label="Email"
                                    type="email"
                                    :value="old('email')"
                                    required
                                    autocomplete="email"
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="password"
                                    label="Password"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                />
                            </div>

                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="password_confirmation"
                                    label="Password confirmation"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                />
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">
                                        Role
                                    </label>

                                    <select
                                        id="role"
                                        name="role"
                                        class="form-select @error('role') is-invalid @enderror"
                                        required
                                    >
                                        <option value="admin" @selected(old('role') === 'admin')>
                                            Admin
                                        </option>

                                        <option value="instructor" @selected(old('role') === 'instructor')>
                                            Instructor
                                        </option>

                                        <option value="student" @selected(old('role', 'student') === 'student')>
                                            Student
                                        </option>
                                    </select>

                                    @error('role')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">
                                        Status
                                    </label>

                                    <select
                                        id="status"
                                        name="status"
                                        class="form-select @error('status') is-invalid @enderror"
                                        required
                                    >
                                        <option value="active" @selected(old('status', 'active') === 'active')>
                                            Active
                                        </option>

                                        <option value="inactive" @selected(old('status') === 'inactive')>
                                            Inactive
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
{{-- 
                        <div class="form-check mb-4">
                            <input
                                type="checkbox"
                                id="send_welcome_email"
                                name="send_welcome_email"
                                value="1"
                                class="form-check-input"
                                @checked(old('send_welcome_email'))
                            >

                            <label for="send_welcome_email" class="form-check-label">
                                Send a welcome email
                            </label>
                        </div> --}}

                        <button type="submit" class="btn btn-primary px-4">
                            Create User
                        </button>

                    </form>
                </div>

            </div>
        </div>
    </div>
</section>
@endsection
