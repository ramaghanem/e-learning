@php
    $course = $course ?? null;
    $objectives = old('learning_objectives', $course?->learning_objectives ?? []);

    if (empty($objectives)) {
        $objectives = [''];
    }
@endphp

<div class="mb-4">
    <span class="form-label d-block" id="objectivesLabel">
        What You'll Learn
    </span>

    <div id="objectivesList" role="group" aria-labelledby="objectivesLabel">
        @foreach ($objectives as $index => $objective)
            <div class="input-group has-validation mb-2 objective-row">
                <input
                    type="text"
                    name="learning_objectives[]"
                    value="{{ $objective }}"
                    class="form-control @error('learning_objectives.' . $index) is-invalid @enderror"
                    placeholder="Learning objective"
                    aria-label="Learning objective"
                >

                <button
                    type="button"
                    class="btn btn-outline-danger objective-remove"
                    aria-label="Remove objective"
                >
                    <i class="bi bi-x-lg"></i>
                </button>

                @error('learning_objectives.' . $index)
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        @endforeach
    </div>

    @error('learning_objectives')
        <div class="invalid-feedback d-block mb-2">
            {{ $message }}
        </div>
    @else
        <div class="form-text mb-2">
            Add at least 3 learning objectives.
        </div>
    @enderror

    <button type="button" class="btn btn-sm btn-outline-primary" id="addObjective">
        <i class="bi bi-plus-lg me-1"></i>
        Add Objective
    </button>

    <template id="objectiveTemplate">
        <div class="input-group has-validation mb-2 objective-row">
            <input
                type="text"
                name="learning_objectives[]"
                class="form-control"
                placeholder="Learning objective"
                aria-label="Learning objective"
            >

            <button
                type="button"
                class="btn btn-outline-danger objective-remove"
                aria-label="Remove objective"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </template>
</div>

<div class="mb-3">
    <label for="requirements" class="form-label">
        Requirements / Prerequisites
    </label>

    <textarea
        id="requirements"
        name="requirements"
        rows="4"
        class="form-control @error('requirements') is-invalid @enderror"
    >{{ old('requirements', $course?->requirements) }}</textarea>

    @error('requirements')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror
</div>

<div class="mb-3">
    <label for="target_audience" class="form-label">
        Who This Course Is For
    </label>

    <textarea
        id="target_audience"
        name="target_audience"
        rows="4"
        class="form-control @error('target_audience') is-invalid @enderror"
    >{{ old('target_audience', $course?->target_audience) }}</textarea>

    @error('target_audience')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4">
        <x-ui.form-input
            name="duration_hours"
            label="Estimated duration (hours)"
            type="number"
            :value="old('duration_hours', $course?->duration_hours)"
            min="0"
            step="1"
        />
    </div>

    <div class="col-md-4">
        <x-ui.form-input
            name="duration_minutes"
            label="Estimated duration (minutes)"
            type="number"
            :value="old('duration_minutes', $course?->duration_minutes)"
            min="0"
            max="59"
            step="1"
        />
    </div>

    <div class="col-md-4">
        <x-ui.form-input
            name="price"
            label="Price"
            type="number"
            :value="old('price', $course?->price)"
            min="0"
            step="0.01"
            help="Enter 0 to make the course free."
        />
    </div>
</div>

@push('scripts')
<script>
    const objectivesList = document.getElementById('objectivesList');
    const objectiveTemplate = document.getElementById('objectiveTemplate');

    document.getElementById('addObjective').addEventListener('click', function () {
        objectivesList.appendChild(objectiveTemplate.content.cloneNode(true));
        objectivesList.lastElementChild.querySelector('input').focus();
    });

    objectivesList.addEventListener('click', function (event) {
        const button = event.target.closest('.objective-remove');

        if (!button) {
            return;
        }

        const row = button.closest('.objective-row');

        // Always keep one input on the page: clear the last one instead of removing it.
        if (objectivesList.querySelectorAll('.objective-row').length === 1) {
            row.querySelector('input').value = '';
            return;
        }

        row.remove();
    });
</script>
@endpush
