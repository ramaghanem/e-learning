{{--
    Delete confirmation modal (same modal as the Users and Categories pages).

    Usage:
        @include('partials.courses.delete-modal', [
            'id' => 'deleteCourseModal-' . $course->id,
            'title' => 'Delete Course',
            'action' => '#',
        ])

    action: where the DELETE form is sent. It is "#" until the routes exist.
--}}
<div
    class="modal fade"
    id="{{ $id }}"
    tabindex="-1"
    aria-labelledby="{{ $id }}-label"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">
                <h2
                    class="modal-title fs-5"
                    id="{{ $id }}-label"
                >
                    {{ $title }}
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
                    action="{{ $action }}"
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
