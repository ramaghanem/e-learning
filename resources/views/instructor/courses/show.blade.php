@extends('layouts.main')

@section('title', 'Course Details')

@section('content')
    @include('partials.courses.show', ['area' => 'instructor'])
@endsection
