@extends('layouts.main')

@section('title', 'Edit Course')

@section('content')
    @include('partials.courses.edit', ['area' => 'admin'])
@endsection
