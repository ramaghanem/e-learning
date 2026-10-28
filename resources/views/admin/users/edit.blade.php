@extends('layouts.main')

@section('title', 'Edit User')

@php
    $ownAdminRole = auth()->id() === $user->id && $user->role === 'admin';
    $role = $ownAdminRole ? 'admin' : old('role', $user->role);
    $status = old('status', $user->status);
@endphp

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Admin</span>
                <h1 class="page-title">Edit User</h1>
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
                          action="{{ route('admin.users.update', $user) }}"
                          enctype="multipart/form-data"
                          novalidate>

                        @csrf
                        @method('PATCH')

                        <div class="picture-field mb-4">
                            <div id="picturePreviewWrap">
                                <x-ui.avatar :user="$user" size="xl" />
                            </div>

                            <div class="flex-grow-1">
                                <label for="profile_picture" class="form-label">
                                    Profile picture
                                </label>

                                <input
                                    type="file"
                                    id="profile_picture"
                                    name="profile_picture"
                                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                    class="form-control @error('profile_picture') is-invalid @enderror"
                                >

                                @error('profile_picture')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @else
                                    <div class="form-text">
                                        JPG or PNG, up to 2MB. Leave empty to keep the current picture.
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="name"
                                    label="Name"
                                    :value="old('name', $user->name)"
                                    required
                                    autocomplete="name"
                                />
                            </div>

                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="email"
                                    label="Email"
                                    type="email"
                                    :value="old('email', $user->email)"
                                    required
                                    autocomplete="email"
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
                                        @disabled($ownAdminRole)
                                    >
                                        <option value="admin" @selected($role === 'admin')>
                                            Admin
                                        </option>

                                        <option value="instructor" @selected($role === 'instructor')>
                                            Instructor
                                        </option>

                                        <option value="student" @selected($role === 'student')>
                                            Student
                                        </option>
                                    </select>

                                    @if ($ownAdminRole)
                                        <input type="hidden" name="role" value="admin">
                                    @endif

                                    @error('role')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @else
                                        @if ($ownAdminRole)
                                            <div class="form-text">
                                                You cannot change your own admin role.
                                            </div>
                                        @endif
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
                                        <option value="active" @selected($status === 'active')>
                                            Active
                                        </option>

                                        <option value="inactive" @selected($status === 'inactive')>
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

@push('scripts')
<script>
    document.getElementById('profile_picture').addEventListener('change', function () {
        const file = this.files[0];

        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const img = document.createElement('img');

        img.src = URL.createObjectURL(file);
        img.alt = 'New profile picture preview';
        img.className = 'avatar avatar-xl';

        document.getElementById('picturePreviewWrap').replaceChildren(img);
    });
</script>
@endpush
