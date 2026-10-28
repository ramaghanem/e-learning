@extends('layouts.main')

@section('title', 'Edit Section')

@section('content')
    @include('partials.courses.sections.edit', [
        'area' => 'instructor',
        'course' => $course,
        'section' => $section,
    ])
@endsection
