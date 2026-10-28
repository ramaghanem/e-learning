@extends('layouts.main')

@section('title', 'Edit Question')

@section('content')
    @include('partials.courses.questions.edit', ['area' => 'admin'])
@endsection
