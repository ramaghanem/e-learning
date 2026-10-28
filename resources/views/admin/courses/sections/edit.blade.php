@extends('layouts.main')

@section('title', 'Edit Section')

@section('content')
    @include('partials.courses.sections.edit', [
        'area' => 'admin',
        'course' => $course,
        'section' => $section,
    ])
@endsection
