@extends('layouts.main')

@section('title', 'Edit Lesson')

@section('content')
    @include('partials.courses.lessons.edit', [
        'area' => 'instructor',
        'course' => $course,
        'lesson' => $lesson,
        'sections' => $sections,
    ])
@endsection
