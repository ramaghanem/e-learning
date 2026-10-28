@extends('layouts.main')

@section('title', 'Create Course')

@section('content')
    @include('partials.courses.create-basic', ['area' => 'admin'])
@endsection
