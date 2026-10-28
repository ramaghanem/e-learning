@extends('layouts.main')

@section('title', 'Create Course')

@section('content')
    @include('partials.courses.create-details', ['area' => 'instructor'])
@endsection
