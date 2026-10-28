@extends('layouts.main')

@section('title', 'Preview Quiz')

@section('content')
    @include('partials.courses.questions.preview', ['area' => 'instructor'])
@endsection
