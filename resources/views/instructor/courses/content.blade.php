@extends('layouts.main')

@section('title', 'Course Content')

@section('content')
    @include('partials.courses.content', ['area' => 'instructor'])
@endsection
