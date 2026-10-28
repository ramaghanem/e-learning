@use('App\Models\QuizQuestion')

@php
    $question = $question ?? null;
    $type = old('type', $question?->type ?? QuizQuestion::TYPE_MCQ);

    // Option rows keep their own key, so the chosen correct answer
    // still points at the same row after removing or adding options.
    if (session()->hasOldInput()) {
        $options = collect(old('options', []))
            ->map(fn ($option) => [
                'id' => $option['id'] ?? null,
                'text' => $option['text'] ?? '',
            ])
            ->all();
        $correctOption = (string) old('correct_option', '');
    } elseif ($question) {
        $options = $question->options
            ->mapWithKeys(fn ($option, $index) => [$index => ['id' => $option->id, 'text' => $option->text]])
            ->all();
        $correctOption = (string) $question->options->search(fn ($option) => $option->is_correct);
    } else {
        $options = [0 => ['id' => null, 'text' => ''], 1 => ['id' => null, 'text' => '']];
        $correctOption = '';
    }

    $optionsError = $errors->first('options')
        ?: collect($errors->getMessages())
            ->filter(fn ($messages, $key) => str_starts_with($key, 'options.'))
            ->flatten()
            ->first();
@endphp

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
@endpush

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="type" class="form-label">
                Question type
            </label>

            <select
                id="type"
                name="type"
                class="form-select @error('type') is-invalid @enderror"
                required
            >
                @foreach (QuizQuestion::TYPES as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            @error('type')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <x-ui.form-input
            name="points"
            label="Points"
            type="number"
            :value="old('points', $question?->points ?? 1)"
            min="1"
            max="100"
            step="1"
            required
        />
    </div>
</div>

<div class="mb-3">
    <label for="question" class="form-label" id="questionLabel">
        Question
    </label>

    {{-- Replaced by the rich text editor when it loads. --}}
    <div id="question-editor" class="d-none bg-body @error('question') border border-danger @enderror"
         style="min-height: 9rem;"></div>

    <textarea
        id="question"
        name="question"
        rows="5"
        class="form-control @error('question') is-invalid @enderror"
        required
    >{{ old('question', $question?->question) }}</textarea>

    @error('question')
        <div class="invalid-feedback d-block">
            {{ $message }}
        </div>
    @enderror
</div>

{{-- Shown only when the question type is "mcq" --}}
<div data-question-type="mcq" @class(['d-none' => $type !== QuizQuestion::TYPE_MCQ])>

    <fieldset class="mb-4">
        <legend class="form-label fs-6 mb-1">Answer options</legend>

        <p class="form-text mt-0 mb-2">
            Add {{ QuizQuestion::MIN_OPTIONS }} to {{ QuizQuestion::MAX_OPTIONS }} options
            and choose the correct answer.
        </p>

        <div id="options-list" data-max-options="{{ QuizQuestion::MAX_OPTIONS }}" data-min-options="{{ QuizQuestion::MIN_OPTIONS }}">
            @foreach ($options as $key => $option)
                @include('partials.courses.questions.option-row', [
                    'key' => $key,
                    'option' => $option,
                    'checked' => (string) $key === $correctOption,
                ])
            @endforeach
        </div>

        @if ($optionsError)
            <div class="invalid-feedback d-block">{{ $optionsError }}</div>
        @endif

        @error('correct_option')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        <div class="d-flex align-items-center gap-3 mt-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-option">
                <i class="bi bi-plus-lg me-1"></i>
                Add Option
            </button>

            <span class="small text-body-secondary" id="options-count" aria-live="polite"></span>
        </div>
    </fieldset>

    <template id="option-template">
        @include('partials.courses.questions.option-row', [
            'key' => '__KEY__',
            'option' => ['id' => null, 'text' => ''],
            'checked' => false,
        ])
    </template>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
    (() => {
        const form = document.getElementById('type').form;

        // Show only the fields for the selected question type.
        const questionType = document.getElementById('type');
        const showQuestionTypeFields = () => {
            document.querySelectorAll('[data-question-type]').forEach((group) => {
                group.classList.toggle('d-none', group.dataset.questionType !== questionType.value);
            });
        };
        questionType.addEventListener('change', showQuestionTypeFields);
        showQuestionTypeFields();

        // Rich text editor for the question. Without it, the plain textarea still works.
        const textarea = document.getElementById('question');
        const editorElement = document.getElementById('question-editor');

        if (window.Quill) {
            const quill = new Quill(editorElement, {
                theme: 'snow',
                placeholder: 'Write the question...',
                // No images: only text formatting.
                formats: ['bold', 'italic', 'underline', 'strike', 'code', 'list', 'link'],
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike', 'code'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'clean'],
                    ],
                },
            });

            if (textarea.value.trim() !== '') {
                quill.clipboard.dangerouslyPasteHTML(textarea.value);
            }

            quill.root.setAttribute('aria-labelledby', 'questionLabel');
            textarea.classList.add('d-none');
            textarea.removeAttribute('required');
            editorElement.classList.remove('d-none');
            document.getElementById('questionLabel').removeAttribute('for');

            form.addEventListener('submit', () => {
                textarea.value = quill.getText().trim() === '' ? '' : quill.getSemanticHTML();
            });
        }

        // Answer options: Add / Remove, between the minimum and the maximum.
        const list = document.getElementById('options-list');
        const template = document.getElementById('option-template');
        const addButton = document.getElementById('add-option');
        const count = document.getElementById('options-count');
        const min = Number(list.dataset.minOptions);
        const max = Number(list.dataset.maxOptions);
        let nextKey = Date.now();

        const refreshOptions = () => {
            const rows = list.querySelectorAll('[data-option-row]');

            rows.forEach((row, index) => {
                const letter = String.fromCharCode(65 + index);
                row.querySelector('[data-option-letter]').textContent = letter;
                row.querySelector('[data-option-text]').setAttribute('aria-label', 'Option ' + letter);
                row.querySelector('[data-option-correct]').setAttribute('aria-label', 'Option ' + letter + ' is the correct answer');
                row.querySelector('[data-remove-option]').setAttribute('aria-label', 'Remove option ' + letter);
                row.querySelector('[data-remove-option]').disabled = rows.length <= min;
            });

            addButton.disabled = rows.length >= max;
            count.textContent = rows.length + ' / ' + max + ' options';
        };

        addButton.addEventListener('click', () => {
            if (list.querySelectorAll('[data-option-row]').length >= max) {
                return;
            }

            const html = template.innerHTML.replaceAll('__KEY__', String(nextKey++));
            list.insertAdjacentHTML('beforeend', html);
            refreshOptions();
            list.lastElementChild.querySelector('[data-option-text]').focus();
        });

        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-option]');

            if (! button || list.querySelectorAll('[data-option-row]').length <= min) {
                return;
            }

            button.closest('[data-option-row]').remove();
            refreshOptions();
            addButton.focus();
        });

        refreshOptions();
    })();
</script>
@endpush
