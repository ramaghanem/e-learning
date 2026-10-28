@extends('layouts.main')

@section('title', 'Add Lesson')

@section('content')
    @include('partials.courses.lessons.create', [
        'area' => 'admin',
        'course' => $course,
        'sections' => $sections,
    ])
@endsection
