@extends('self-service::layout')

@section('title', 'You\'re already a member')

@section('message')
You already have access to {{ $service }}.
@endsection
