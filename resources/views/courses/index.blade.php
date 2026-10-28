@extends('layouts.main')

@section('title', 'Browse Courses')

@php
    $selectedCategories = array_map('strval', array_filter((array) request('category', [])));
    $selectedDifficulties = (array) request('difficulty', []);
    $freeOnly = request()->boolean('free_only');

    $maxPrice = is_numeric(request('max_price'))
        ? (int) min(max(request('max_price'), 0), $priceCeiling)
        : $priceCeiling;

    $hasFilters = request()->filled('search') || $selectedCategories || $selectedDifficulties
        || $freeOnly || $maxPrice < $priceCeiling;
@endphp

@section('content')
<section class="page-section">
    <div class="container">

        <div class="page-heading">
            <div>
                <span class="eyebrow">Courses</span>
                <h1 class="page-title">Browse Courses</h1>
            </div>
        </div>

        <form method="GET" action="{{ route('courses.index') }}">

            <div class="row g-4">

                {{-- Filters --}}
                <div class="col-lg-3">

                    <button
                        class="btn btn-outline-primary w-100 d-lg-none"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#courseFilters"
                        aria-expanded="false"
                        aria-controls="courseFilters"
                    >
                        <i class="bi bi-funnel me-1"></i>
                        Filters
                    </button>

                    <div class="collapse d-lg-block mt-3 mt-lg-0" id="courseFilters">
                        <div class="panel sticky-panel">

                            <h2 class="filter-heading">Category</h2>

                            {{-- Fixed height with its own scrollbar, so a long list of categories stays tidy. --}}
                            <div class="filter-scroll mb-4" role="group" aria-label="Categories">
                                @foreach ($categories as $category)
                                    <div class="form-check mb-1">
                                        <input
                                            type="checkbox"
                                            id="category_{{ $category->id }}"
                                            name="category[]"
                                            value="{{ $category->id }}"
                                            class="form-check-input"
                                            @checked(in_array((string) $category->id, $selectedCategories, true))
                                        >
                                        <label for="category_{{ $category->id }}" class="form-check-label d-flex justify-content-between gap-2">
                                            <span>{{ $category->name }}</span>
                                            <span class="small text-body-secondary">{{ $category->courses_count ?? 0 }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <h2 class="filter-heading">Difficulty</h2>

                            <div class="mb-4" role="group" aria-label="Difficulty">
                                @foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)
                                    <div class="form-check mb-1">
                                        <input
                                            type="checkbox"
                                            id="difficulty_{{ $value }}"
                                            name="difficulty[]"
                                            value="{{ $value }}"
                                            class="form-check-input"
                                            @checked(in_array($value, $selectedDifficulties, true))
                                        >
                                        <label for="difficulty_{{ $value }}" class="form-check-label">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>

                            <h2 class="filter-heading">Price</h2>

                            <div class="form-check mb-3">
                                <input
                                    type="checkbox"
                                    id="free_only"
                                    name="free_only"
                                    value="1"
                                    class="form-check-input"
                                    @checked($freeOnly)
                                >
                                <label for="free_only" class="form-check-label">Free only</label>
                            </div>

                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <label for="max_price" class="form-label small text-body-secondary mb-0">
                                    Maximum price
                                </label>

                                <span class="small fw-semibold" id="maxPriceValue" aria-live="polite">
                                    {{ $maxPrice >= $priceCeiling ? 'Any price' : '$' . $maxPrice }}
                                </span>
                            </div>

                            {{-- Disabled while "Free only" is ticked, so it is not sent then. --}}
                            <input
                                type="range"
                                id="max_price"
                                name="max_price"
                                min="0"
                                max="{{ $priceCeiling }}"
                                step="1"
                                value="{{ $maxPrice }}"
                                class="form-range"
                                data-ceiling="{{ $priceCeiling }}"
                                @disabled($freeOnly)
                            >

                            <div class="d-flex justify-content-between small text-body-secondary mb-4">
                                <span>$0</span>
                                <span>${{ $priceCeiling }}</span>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    Apply Filters
                                </button>

                                <a href="{{ route('courses.index') }}" class="btn btn-outline-primary">
                                    Clear All
                                </a>
                            </div>

                        </div>
                    </div>

                </div>

                {{-- Search, sort and results --}}
                <div class="col-lg-9">

                    <div class="d-flex flex-column flex-md-row gap-2 mb-3">

                        <div class="input-group flex-grow-1">
                            <input
                                type="search"
                                name="search"
                                value="{{ request('search') }}"
                                class="form-control"
                                placeholder="Search courses"
                                aria-label="Search courses"
                            >

                            <button type="submit" class="btn btn-primary" aria-label="Search">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>

                        <select class="form-select w-auto" aria-label="Sort courses" disabled>
                            <option>Most Popular</option>
                            <option selected>Newest</option>
                            <option>Highest Rated</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                        </select>

                    </div>

                    @if ($courses instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $courses->total() > 0)
                        <p class="small text-body-secondary mb-3">
                            Showing {{ $courses->firstItem() }}–{{ $courses->lastItem() }} of {{ $courses->total() }}
                            {{ Str::plural('course', $courses->total()) }}
                        </p>
                    @endif

                    @if ($courses->isEmpty())

                        <x-ui.empty-state
                            icon="bi-journal-x"
                            title="No courses available"
                        >
                            @if ($hasFilters)
                                No published courses match your search or filters.
                            @else
                                There are no published courses yet. Please check back soon.
                            @endif
                        </x-ui.empty-state>

                    @else

                        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4">

                            @foreach ($courses as $course)
                                <div class="col">
                                    @include('partials.courses.card', ['course' => $course])
                                </div>
                            @endforeach

                        </div>

                        @if ($courses instanceof \Illuminate\Contracts\Pagination\Paginator)
                            <div class="mt-4">
                                {{ $courses->links('pagination::bootstrap-5') }}
                            </div>
                        @endif

                    @endif

                </div>

            </div>

        </form>

    </div>
</section>
@endsection

@push('scripts')
<script>
    const maxPriceInput = document.getElementById('max_price');
    const maxPriceValue = document.getElementById('maxPriceValue');
    const freeOnlyInput = document.getElementById('free_only');

    const showMaxPrice = () => {
        maxPriceValue.textContent = Number(maxPriceInput.value) >= Number(maxPriceInput.dataset.ceiling)
            ? 'Any price'
            : '$' + maxPriceInput.value;
    };

    maxPriceInput.addEventListener('input', showMaxPrice);

    // "Free only" already means price 0, so the slider is not needed then.
    freeOnlyInput.addEventListener('change', () => {
        maxPriceInput.disabled = freeOnlyInput.checked;
    });
</script>
@endpush
