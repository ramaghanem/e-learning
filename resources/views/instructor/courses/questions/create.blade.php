@extends('layouts.main')

@section('title', 'Add Question')

@section('content')
    @include('partials.courses.questions.create', ['area' => 'instructor'])
@endsection
