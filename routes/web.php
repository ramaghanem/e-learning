<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\QuizQuestionController as AdminQuizQuestionController;
use App\Http\Controllers\Admin\SectionController as AdminSectionController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonCompletionController;
use App\Http\Controllers\Instructor\CourseController as InstructorCourseController;
use App\Http\Controllers\Instructor\LessonController as InstructorLessonController;
use App\Http\Controllers\Instructor\QuizQuestionController as InstructorQuizQuestionController;
use App\Http\Controllers\Instructor\SectionController as InstructorSectionController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\StudentStatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return match (auth()->user()->role) {
        'admin'      => redirect('/admin/dashboard'),
        'instructor' => redirect('/instructor/dashboard'),
        'student'    => redirect('/student/dashboard'),
        default      => abort(403),
    };

})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->get('/admin/dashboard', function () {
    return view('dashboards.admin');
});

Route::middleware(['auth', 'role:instructor'])->get('/instructor/dashboard', function () {
    return view('dashboards.instructor');
});

Route::middleware(['auth', 'role:student'])->get('/student/dashboard', function () {
    return view('dashboards.student');
});
require __DIR__ . '/auth.php';

Route::middleware(['auth', 'role:admin'])->get('/admin/dashboard', [DashboardController::class, 'admin']);

Route::middleware(['auth', 'role:instructor'])->get('/instructor/dashboard', [DashboardController::class, 'instructor']);

Route::middleware(['auth', 'role:student'])->get('/student/dashboard', [DashboardController::class, 'student']);

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::resource('users', UserController::class);
    Route::resource('categories', CategoryController::class);

    Route::get('courses/{course}/details', [
        AdminCourseController::class, 'createDetails',
    ])->name('courses.details');

    Route::post('courses/{course}/details', [
        AdminCourseController::class, 'storeDetails',
    ])->name('courses.details.store');

    Route::get('courses/{course}/content', [
        AdminCourseController::class, 'content',
    ])->name('courses.content');

    Route::get('courses/{course}/preview/{lesson?}', [
        AdminCourseController::class, 'preview',
    ])->name('courses.preview');
    Route::resource('courses', AdminCourseController::class);

    Route::patch('courses/{course}/publish', [AdminCourseController::class, 'publish'])
    ->name('courses.publish');

    Route::post('courses/{course}/sections', [AdminSectionController::class, 'store'])
        ->name('courses.sections.store');

    Route::get('/courses/{course}/sections/{section}/edit', [
        SectionController::class, 'edit',
    ])->name('courses.sections.edit');

    Route::patch('courses/{course}/sections/{section}', [AdminSectionController::class, 'update'])
        ->name('courses.sections.update');

    Route::delete('courses/{course}/sections/{section}', [AdminSectionController::class, 'destroy'])
        ->name('courses.sections.destroy');

    Route::patch('courses/{course}/sections/{section}/move', [AdminSectionController::class, 'move'])
        ->name('courses.sections.move');

    Route::get('courses/{course}/lessons/create', [AdminLessonController::class, 'create'])
        ->name('courses.lessons.create');

    Route::post('courses/{course}/lessons', [AdminLessonController::class, 'store'])
        ->name('courses.lessons.store');

    Route::get('courses/{course}/lessons/{lesson}/edit', [AdminLessonController::class, 'edit'])
        ->name('courses.lessons.edit');

    Route::patch('courses/{course}/lessons/{lesson}', [AdminLessonController::class, 'update'])
        ->name('courses.lessons.update');

    Route::delete('courses/{course}/lessons/{lesson}', [AdminLessonController::class, 'destroy'])
        ->name('courses.lessons.destroy');

    Route::patch('courses/{course}/lessons/{lesson}/move', [AdminLessonController::class, 'move'])
        ->name('courses.lessons.move');

    Route::get('courses/{course}/lessons/{lesson}/questions', [AdminQuizQuestionController::class, 'index'])
        ->name('courses.lessons.questions.index');

    Route::get('courses/{course}/lessons/{lesson}/questions/create', [AdminQuizQuestionController::class, 'create'])
        ->name('courses.lessons.questions.create');

    Route::get('courses/{course}/lessons/{lesson}/questions/preview', [AdminQuizQuestionController::class, 'preview'])
        ->name('courses.lessons.questions.preview');

    Route::post('courses/{course}/lessons/{lesson}/questions', [AdminQuizQuestionController::class, 'store'])
        ->name('courses.lessons.questions.store');

    Route::patch('courses/{course}/lessons/{lesson}/questions/reorder', [AdminQuizQuestionController::class, 'reorder'])
        ->name('courses.lessons.questions.reorder');

    Route::get('courses/{course}/lessons/{lesson}/questions/{question}/edit', [AdminQuizQuestionController::class, 'edit'])
        ->name('courses.lessons.questions.edit');

    Route::patch('courses/{course}/lessons/{lesson}/questions/{question}', [AdminQuizQuestionController::class, 'update'])
        ->name('courses.lessons.questions.update');

    Route::delete('courses/{course}/lessons/{lesson}/questions/{question}', [AdminQuizQuestionController::class, 'destroy'])
        ->name('courses.lessons.questions.destroy');
});

Route::middleware(['auth', 'role:instructor'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('courses/{course}/details', [
        InstructorCourseController::class, 'createDetails',
    ])->name('courses.details');

    Route::post('courses/{course}/details', [
        InstructorCourseController::class, 'storeDetails',
    ])->name('courses.details.store');

    Route::get('courses/{course}/content', [
        InstructorCourseController::class, 'content',
    ])->name('courses.content');

    Route::get('courses/{course}/preview/{lesson?}', [
        InstructorCourseController::class, 'preview',
    ])->name('courses.preview');

    Route::get('courses/{course}/analytics', [
        InstructorCourseController::class, 'analytics',
    ])->name('courses.analytics');

    Route::resource('courses', InstructorCourseController::class);

    Route::patch('courses/{course}/publish', [InstructorCourseController::class, 'publish'])
    ->name('courses.publish');

    Route::post('courses/{course}/sections', [InstructorSectionController::class, 'store'])
        ->name('courses.sections.store');

    Route::get('/courses/{course}/sections/{section}/edit', [
        SectionController::class, 'edit',
    ])->name('courses.sections.edit');

    Route::patch('courses/{course}/sections/{section}', [InstructorSectionController::class, 'update'])
        ->name('courses.sections.update');

    Route::delete('courses/{course}/sections/{section}', [InstructorSectionController::class, 'destroy'])
        ->name('courses.sections.destroy');

    Route::patch('courses/{course}/sections/{section}/move', [InstructorSectionController::class, 'move'])
        ->name('courses.sections.move');

    Route::get('courses/{course}/lessons/create', [InstructorLessonController::class, 'create'])
        ->name('courses.lessons.create');

    Route::post('courses/{course}/lessons', [InstructorLessonController::class, 'store'])
        ->name('courses.lessons.store');

    Route::get('courses/{course}/lessons/{lesson}/edit', [InstructorLessonController::class, 'edit'])
        ->name('courses.lessons.edit');

    Route::patch('courses/{course}/lessons/{lesson}', [InstructorLessonController::class, 'update'])
        ->name('courses.lessons.update');

    Route::delete('courses/{course}/lessons/{lesson}', [InstructorLessonController::class, 'destroy'])
        ->name('courses.lessons.destroy');

    Route::patch('courses/{course}/lessons/{lesson}/move', [InstructorLessonController::class, 'move'])
        ->name('courses.lessons.move');

    Route::get('courses/{course}/lessons/{lesson}/questions', [InstructorQuizQuestionController::class, 'index'])
        ->name('courses.lessons.questions.index');

    Route::get('courses/{course}/lessons/{lesson}/questions/create', [InstructorQuizQuestionController::class, 'create'])
        ->name('courses.lessons.questions.create');

    Route::get('courses/{course}/lessons/{lesson}/questions/preview', [InstructorQuizQuestionController::class, 'preview'])
        ->name('courses.lessons.questions.preview');

    Route::post('courses/{course}/lessons/{lesson}/questions', [InstructorQuizQuestionController::class, 'store'])
        ->name('courses.lessons.questions.store');

    Route::patch('courses/{course}/lessons/{lesson}/questions/reorder', [InstructorQuizQuestionController::class, 'reorder'])
        ->name('courses.lessons.questions.reorder');

    Route::get('courses/{course}/lessons/{lesson}/questions/{question}/edit', [InstructorQuizQuestionController::class, 'edit'])
        ->name('courses.lessons.questions.edit');

    Route::patch('courses/{course}/lessons/{lesson}/questions/{question}', [InstructorQuizQuestionController::class, 'update'])
        ->name('courses.lessons.questions.update');

    Route::delete('courses/{course}/lessons/{lesson}/questions/{question}', [InstructorQuizQuestionController::class, 'destroy'])
        ->name('courses.lessons.questions.destroy');
});

Route::get('/courses', [CourseController::class, 'index'])
    ->name('courses.index');
Route::get('/courses/{course}', [CourseController::class, 'show'])
    ->name('courses.show');
Route::get('/instructors/{instructor}', [InstructorController::class, 'show'])
    ->name('instructors.show');

Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'store'])
    ->name('courses.enroll');

Route::get('/courses/{course}/learn/{lesson?}', [CourseController::class, 'learn'])
    ->name('courses.learn');

Route::middleware(['auth', 'role:student'])->get('/student/courses', [EnrollmentController::class, 'index'])
    ->name('student.courses.index');

Route::middleware(['auth', 'role:student'])->get('/student/statistics', [StudentStatisticsController::class, 'show'])
    ->name('student.statistics');


Route::middleware(['auth', 'role:student'])->post('/courses/{course}/learn/{lesson}/complete', [LessonCompletionController::class, 'store'])
    ->name('courses.lessons.complete');

Route::middleware(['auth', 'role:student'])->post('/courses/{course}/learn/{lesson}/quiz/start', [StudentQuizController::class, 'start'])
    ->name('courses.quizzes.start');

Route::middleware(['auth', 'role:student'])->get('/courses/{course}/learn/{lesson}/quiz/attempts/{attempt}', [StudentQuizController::class, 'show'])
    ->name('courses.quizzes.attempt');

Route::middleware(['auth', 'role:student'])->patch('/courses/{course}/learn/{lesson}/quiz/attempts/{attempt}/answers', [StudentQuizController::class, 'saveAnswer'])
    ->name('courses.quizzes.answers');

Route::middleware(['auth', 'role:student'])->post('/courses/{course}/learn/{lesson}/quiz/attempts/{attempt}/submit', [StudentQuizController::class, 'submit'])
    ->name('courses.quizzes.submit');

Route::middleware(['auth', 'role:student'])->get('/courses/{course}/learn/{lesson}/quiz/attempts/{attempt}/results', [StudentQuizController::class, 'results'])
    ->name('courses.quizzes.results');

Route::middleware(['auth', 'role:student'])->get('/courses/{course}/certificate', [CertificateController::class, 'show'])
    ->name('courses.certificate');
