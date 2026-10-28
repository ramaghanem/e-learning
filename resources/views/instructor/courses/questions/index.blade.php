@extends('layouts.main')

@section('title', 'Quiz Questions')

@section('content')
    @include('partials.courses.questions.index', ['area' => 'instructor'])
@endsection
