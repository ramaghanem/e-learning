@extends('layouts.main')

@section('title', 'Edit Profile')

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                {{-- <span class="eyebrow">Account</span> --}}
                <h1 class="page-title">Edit Profile</h1>
            </div>

            <a href="{{ route('profile.show') }}" class="btn btn-outline-primary">
                <i class="bi bi-person me-1"></i>
                View Profile
            </a>
        </div>

        <div class="row g-4">



            <div class="col-lg-9">

                <div class="panel" id="profile-info">
                    <h2 class="panel-title">Profile information</h2>

                    <p class="text-body-secondary">
                        Update your name, contact details, bio and profile picture.
                    </p>

                    @if (session('status') === 'profile-updated')
                        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            Your profile was updated successfully.
                        </div>
                    @endif

                    <form method="POST"
                          action="{{ route('profile.update') }}"
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
                                        JPG or PNG, up to 2MB. Leave empty to keep your current picture.
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="name"
                                    label="Full name"
                                    :value="old('name', $user->name)"
                                    required
                                    autocomplete="name"
                                />
                            </div>

                            <div class="col-md-6">
                                <x-ui.form-input
                                    name="phone"
                                    label="Phone"
                                    type="tel"
                                    :value="old('phone', $user->phone)"
                                    autocomplete="tel"
                                    placeholder="Your phone number"
                                />
                            </div>
                        </div>

                        <x-ui.form-input
                            name="email"
                            label="Email"
                            type="email"
                            :value="old('email', $user->email)"
                            required
                            autocomplete="username"
                        />

                        <div class="mb-4">
                            <div class="d-flex justify-content-between">
                                <label for="bio" class="form-label">
                                    Bio
                                </label>

                                <small class="text-body-secondary">
                                    <span id="bioCount">0</span>/500
                                </small>
                            </div>

                            <textarea
                                id="bio"
                                name="bio"
                                rows="4"
                                maxlength="500"
                                class="form-control @error('bio') is-invalid @enderror"
                                placeholder="Tell students a little about yourself..."
                            >{{ old('bio', $user->bio ?? '') }}</textarea>

                            @error('bio')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            Save changes
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

    const bio = document.getElementById('bio');
    const bioCount = document.getElementById('bioCount');

    const updateCount = () => {
        bioCount.textContent = bio.value.length;
    };

    bio.addEventListener('input', updateCount);
    updateCount();
</script>
@endpush
