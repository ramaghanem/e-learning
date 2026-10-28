<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class StudentQuizController extends Controller
{

    public function start(Course $course, Lesson $lesson)
    {
        $this->ensureQuizOfPublishedCourse($course, $lesson);

        $student = auth()->user();
        $enrollment = $course->enrollmentFor($student);

        if (! $enrollment) {
            return redirect()
                ->route('courses.show', $course)
                ->with('error', 'Enroll in this course to open its lessons.');
        }

        $learnUrl = route('courses.learn', [$course, $lesson]);

        if (! $lesson->questions()->exists()) {
            return redirect($learnUrl)->with('error', 'This quiz has no questions yet.');
        }

        $attempt = DB::transaction(function () use ($enrollment, $lesson, $student) {

            Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->first();

            if ($open = QuizAttempt::openAttemptFor($lesson, $student)) {
                return $open;
            }

            $used = $lesson->quizAttempts()->where('student_id', $student->id)->count();

            if ($lesson->quizAttemptsLeft($used) === 0) {
                return null;
            }

            return QuizAttempt::start($lesson, $student);
        });

        if (! $attempt) {
            return redirect($learnUrl)->with('error', 'You have used all your attempts for this quiz.');
        }

        return redirect()->route('courses.quizzes.attempt', [$course, $lesson, $attempt]);
    }


    public function show(Course $course, Lesson $lesson, QuizAttempt $attempt)
    {
        $this->ensureQuizOfPublishedCourse($course, $lesson);
        $this->ensureOwnAttempt($lesson, $attempt);

        if (! $course->enrollmentFor(auth()->user())) {
            return redirect()
                ->route('courses.show', $course)
                ->with('error', 'Enroll in this course to open its lessons.');
        }

        $resultsUrl = route('courses.quizzes.results', [$course, $lesson, $attempt]);

        if ($attempt->isInProgress() && $attempt->hasExpired()) {
            $attempt->grade(timedOut: true);

            return redirect($resultsUrl)->with('error', "Time's up! Your attempt was submitted.");
        }

        // Already submitted: show its result instead.
        if ($attempt->isSubmitted()) {
            return redirect($resultsUrl);
        }

        $answers = $attempt->answers()->get();

        // The timer counts down from the time left on the server, not the student's clock.
        $remainingMs = $attempt->remainingMilliseconds();

        return view('student.quizzes.attempt', compact('course', 'lesson', 'attempt', 'answers', 'remainingMs'));
    }

    public function submit(Course $course, Lesson $lesson, QuizAttempt $attempt)
    {
        $this->ensureQuizOfPublishedCourse($course, $lesson);
        $this->ensureOwnAttempt($lesson, $attempt);

        if (! $course->enrollmentFor(auth()->user())) {
            return redirect()
                ->route('courses.show', $course)
                ->with('error', 'Enroll in this course to open its lessons.');
        }

        $resultsUrl = route('courses.quizzes.results', [$course, $lesson, $attempt]);

        if ($attempt->isSubmitted()) {
            return redirect($resultsUrl)->with('error', 'This attempt has already been submitted.');
        }

        // Sent after 0:00 (by the timer, or late): it counts as time's up.
        $timedOut = $attempt->hasExpired();
        $attempt->grade(timedOut: $timedOut);

        return $timedOut
            ? redirect($resultsUrl)->with('error', "Time's up! Your attempt was submitted.")
            : redirect($resultsUrl);
    }


    public function results(Course $course, Lesson $lesson, QuizAttempt $attempt)
    {
        $this->ensureQuizOfPublishedCourse($course, $lesson);
        $this->ensureOwnAttempt($lesson, $attempt);

        $student = auth()->user();
        $enrollment = $course->enrollmentFor($student);

        if (! $enrollment) {
            return redirect()
                ->route('courses.show', $course)
                ->with('error', 'Enroll in this course to open its lessons.');
        }

        if ($attempt->isInProgress()) {

            if (! $attempt->hasExpired()) {
                return redirect()->route('courses.quizzes.attempt', [$course, $lesson, $attempt]);
            }

            $attempt->grade(timedOut: true);
        }


        $lessons = $course->load('sections.lessons')->sections->flatMap->lessons->values();
        $index = $lessons->search(fn ($item) => $item->id === $lesson->id);
        $nextLesson = $lessons[$index + 1] ?? null;

        // "Review Course Material": the lesson before the quiz, or the first lesson.
        $reviewLesson = $index > 0 ? $lessons[$index - 1] : null;

        $attemptsUsed = $lesson->quizAttempts()->where('student_id', $student->id)->count();
        $attemptsLeft = $lesson->quizAttemptsLeft($attemptsUsed);

        
        $showReview = (bool) $lesson->quiz_show_review;
        $answers = $showReview ? $attempt->answers()->get() : collect();

        $enrollment->refresh();

        return view('student.quizzes.results', compact(
            'course', 'lesson', 'attempt', 'enrollment', 'nextLesson', 'reviewLesson',
            'attemptsLeft', 'showReview', 'answers'
        ));
    }

    /**
     * The page sends it with fetch() and gets JSON back.
     */
    public function saveAnswer(Request $request, Course $course, Lesson $lesson, QuizAttempt $attempt)
    {
        $this->ensureQuizOfPublishedCourse($course, $lesson);
        $this->ensureOwnAttempt($lesson, $attempt);

        $resultsUrl = route('courses.quizzes.results', [$course, $lesson, $attempt]);

        if (! $course->enrollmentFor(auth()->user())) {
            return $this->answerNotSaved($request, 'Enroll in this course to open its lessons.', 403, route('courses.show', $course));
        }

        $data = $request->validate([
            'answer_id' => 'required|integer',
            'option_id' => 'required|integer',
        ]);


        $result = DB::transaction(function () use ($attempt, $data) {
            $locked = QuizAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked->isSubmitted()) {
                return 'submitted';
            }

            if (! $locked->acceptsAnswers()) {
                return 'expired';
            }

            $answer = $locked->answers()->whereKey($data['answer_id'])->first();

            if (! $answer) {
                throw ValidationException::withMessages(['answer_id' => 'This question is not part of your attempt.']);
            }

            if (! $answer->hasOption((int) $data['option_id'])) {
                throw ValidationException::withMessages(['option_id' => 'Choose one of the options of this question.']);
            }

            $answer->update([
                'selected_option_id' => (int) $data['option_id'],
                'answered_at' => now(),
            ]);

            return 'saved';
        });

        if ($result === 'expired') {
            $attempt->grade(timedOut: true);

            return $this->answerNotSaved($request, "Time's up! Your attempt was submitted.", 409, $resultsUrl);
        }

        if ($result === 'submitted') {
            return $this->answerNotSaved($request, 'This attempt has already been submitted.', 409, $resultsUrl);
        }

        if (! $request->expectsJson()) {
            return redirect()->route('courses.quizzes.attempt', [$course, $lesson, $attempt]);
        }

        return response()->json([
            'message' => 'Answer saved.',
            'answerId' => (int) $data['answer_id'],
            'optionId' => (int) $data['option_id'],
            'answered' => $attempt->answers()->whereNotNull('selected_option_id')->count(),
            'total' => $attempt->answers()->count(),
        ]);
    }

    /**
     * The answer could not be saved: JSON for the page, or a redirect without JavaScript.
     */
    private function answerNotSaved(Request $request, string $message, int $status, string $redirect)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $redirect], $status);
        }

        return redirect($redirect)->with('error', $message);
    }

    /**
     * The attempt must be of this quiz and belong to the student.
     */
    private function ensureOwnAttempt(Lesson $lesson, QuizAttempt $attempt): void
    {
        abort_unless((int) $attempt->lesson_id === (int) $lesson->id, 404);
        abort_unless((int) $attempt->student_id === (int) auth()->id(), 403);
    }

    private function ensureQuizOfPublishedCourse(Course $course, Lesson $lesson): void
    {
        abort_unless($course->status === 'published', 404);

        abort_unless(
            $lesson->type === 'quiz'
                && $lesson->section()->where('course_id', $course->id)->exists(),
            404
        );
    }
}
