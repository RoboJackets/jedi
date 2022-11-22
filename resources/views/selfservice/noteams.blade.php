@extends('selfservice/layout')

@section('title', 'Unauthorized')

@section('message')
You are not a member of any teams yet. <a href="{{ config('apiary.server') }}/teams">Join a team in MyRoboJackets</a>, then try again.
@endsection
