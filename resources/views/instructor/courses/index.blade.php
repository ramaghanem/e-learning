@extends('layouts.main')

@section('title', 'Courses')

@section('content')
    @include('partials.courses.list', ['area' => 'instructor'])
@endsection
