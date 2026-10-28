@extends('layouts.main')

@section('title', 'Preview Course')

@section('content')
    @include('partials.courses.preview', ['area' => 'instructor'])
@endsection
