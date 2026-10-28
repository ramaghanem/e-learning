{{--
    One answer option of the question form.

    Expects:
        $key       the row key used in the input names (options[key][...])
        $option    ['id' => ?int, 'text' => string]
        $checked   true when this option is the correct answer
--}}
<div class="input-group mb-2" data-option-row>
    <div class="input-group-text">
        <input
            type="radio"
            name="correct_option"
            value="{{ $key }}"
            class="form-check-input mt-0"
            data-option-correct
            @checked($checked)
        >
    </div>

    <span class="input-group-text fw-semibold" data-option-letter></span>

    @if ($option['id'])
        <input type="hidden" name="options[{{ $key }}][id]" value="{{ $option['id'] }}">
    @endif

    <input
        type="text"
        name="options[{{ $key }}][text]"
        value="{{ $option['text'] }}"
        maxlength="500"
        placeholder="Option text"
        @class(['form-control', 'is-invalid' => $errors->has('options.' . $key . '.text')])
        data-option-text
        required
    >

    <button type="button" class="btn btn-outline-danger" data-remove-option>
        <i class="bi bi-x-lg"></i>
        <span class="d-none d-sm-inline ms-1">Remove</span>
    </button>
</div>
