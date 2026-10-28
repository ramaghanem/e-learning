<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


abstract class CourseQuizQuestionController extends Controller
{
    abstract protected function authorizeCourse(Course $course): void;

    abstract protected function routePrefix(): string;

    private function indexRoute(): string
    {
        return $this->routePrefix() . '.courses.lessons.questions.index';
    }

    public function index(Course $course, Lesson $lesson)
    {
        $this->authorizeQuiz($course, $lesson);

        $questions = $lesson->questions()->get();

        return view(
            $this->routePrefix() . '.courses.questions.index',
            compact('course', 'lesson', 'questions')
        );
    }

    public function create(Course $course, Lesson $lesson)
    {
        $this->authorizeQuiz($course, $lesson);

        return view(
            $this->routePrefix() . '.courses.questions.create',
            compact('course', 'lesson')
        );
    }

    public function edit(Course $course, Lesson $lesson, QuizQuestion $question)
    {
        $this->authorizeQuiz($course, $lesson, $question);

        $question->load('options');

        return view(
            $this->routePrefix() . '.courses.questions.edit',
            compact('course', 'lesson', 'question')
        );
    }

    public function preview(Course $course, Lesson $lesson)
    {
        $this->authorizeQuiz($course, $lesson);

        $questions = $lesson->questions()->with('options')->get();

        return view(
            $this->routePrefix() . '.courses.questions.preview',
            compact('course', 'lesson', 'questions')
        );
    }

    public function store(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeQuiz($course, $lesson);

        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($lesson, $data) {
            $question = $lesson->questions()->create([
                'type' => $data['type'],
                'question' => $data['question'],
                'points' => $data['points'],
                'order' => ((int) $lesson->questions()->max('order')) + 1,
            ]);

            $this->saveOptions($question, $data);
        });

        return redirect()
            ->route($this->indexRoute(), [$course, $lesson])
            ->with('success', 'Question added successfully.');
    }

    public function update(
        Request $request,
        Course $course,
        Lesson $lesson,
        QuizQuestion $question
    ) {
        $this->authorizeQuiz($course, $lesson, $question);

        $data = $this->validateQuestion($request, $question);

        DB::transaction(function () use ($question, $data) {
            $question->update([
                'type' => $data['type'],
                'question' => $data['question'],
                'points' => $data['points'],
            ]);

            $this->saveOptions($question, $data);
        });

        return redirect()
            ->route($this->indexRoute(), [$course, $lesson])
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Course $course, Lesson $lesson, QuizQuestion $question)
    {
        $this->authorizeQuiz($course, $lesson, $question);

        $question->delete();

        return redirect()
            ->route($this->indexRoute(), [$course, $lesson])
            ->with('success', 'Question deleted successfully.');
    }

    /**
     * Saves the order after dragging and dropping the questions.
     * The page sends every question id of the quiz, in the new order.
     */
    public function reorder(Request $request, Course $course, Lesson $lesson)
    {
        $this->authorizeQuiz($course, $lesson);

        $data = $request->validate([
            'questions' => 'required|array',
            'questions.*' => 'required|integer|distinct',
        ]);

        $ids = array_map('intval', $data['questions']);

        DB::transaction(function () use ($lesson, $ids) {
            $currentIds = $lesson->questions()->lockForUpdate()->pluck('id')->all();

            $sorted = $ids;
            sort($sorted);
            sort($currentIds);

            if ($sorted !== $currentIds) {
                throw ValidationException::withMessages([
                    'questions' => 'The question list is out of date. Reload the page and try again.',
                ]);
            }

            foreach ($ids as $index => $id) {
                $lesson->questions()->whereKey($id)->update(['order' => $index + 1]);
            }
        });

        $message = 'Question order updated.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route($this->indexRoute(), [$course, $lesson])
            ->with('success', $message);
    }

    /**
     * The lesson must be a quiz of this course, and the question must belong to it.
     */
    private function authorizeQuiz(
        Course $course,
        Lesson $lesson,
        ?QuizQuestion $question = null
    ): void {
        $this->authorizeCourse($course);

        abort_unless(
            $lesson->type === 'quiz'
                && $lesson->section()->where('course_id', $course->id)->exists(),
            404
        );

        if ($question) {
            abort_unless((int) $question->lesson_id === (int) $lesson->id, 404);
        }
    }

    private function validateQuestion(Request $request, ?QuizQuestion $question = null): array
    {
        // Each option row keeps its own key (options[key][text]),
        // and the correct answer is the key of the chosen row.
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(QuizQuestion::TYPES))],
            'question' => 'required|string|max:20000',
            'points' => 'required|integer|min:1|max:100',
            'options' => 'required|array|min:' . QuizQuestion::MIN_OPTIONS . '|max:' . QuizQuestion::MAX_OPTIONS,
            'options.*' => 'required|array',
            'options.*.text' => 'required|string|max:500',
            'options.*.id' => [
                'nullable',
                'integer',
                'distinct',
                // Only options of the question being edited can be kept.
                Rule::exists('quiz_question_options', 'id')
                    ->where('quiz_question_id', $question?->id ?? 0),
            ],
            'correct_option' => 'required|string|max:20',
        ], [
            'question.required' => 'Write the question.',
            'options.required' => 'Add the answer options.',
            'options.min' => 'Add at least :min options.',
            'options.max' => 'A question can have at most :max options.',
            'options.*.text.required' => 'Write the text of every option.',
            'options.*.text.max' => 'An option can be at most :max characters.',
            'correct_option.required' => 'Choose the correct answer.',
        ]);

        $data['question'] = QuizQuestion::cleanHtml($data['question']);

        if (QuizQuestion::isBlankHtml($data['question'])) {
            throw ValidationException::withMessages([
                'question' => 'Write the question.',
            ]);
        }

        if (! array_key_exists((string) $data['correct_option'], $data['options'])) {
            throw ValidationException::withMessages([
                'correct_option' => 'Choose the correct answer.',
            ]);
        }

        return $data;
    }

    /**
     * Saves the options in the order they were sent. Existing options are
     * updated, new ones created, and options removed from the form deleted.
     */
    private function saveOptions(QuizQuestion $question, array $data): void
    {
        $keptIds = collect($data['options'])->pluck('id')->filter()->all();

        $question->options()->whereNotIn('id', $keptIds)->delete();

        $order = 0;

        foreach ($data['options'] as $key => $option) {
            $attributes = [
                'text' => trim($option['text']),
                'is_correct' => (string) $key === (string) $data['correct_option'],
                'order' => ++$order,
            ];

            if (! empty($option['id'])) {
                $question->options()->whereKey($option['id'])->update($attributes);
            } else {
                $question->options()->create($attributes);
            }
        }
    }
}
