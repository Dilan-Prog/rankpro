@extends('layouts.app')

@section('title', 'RankPro · Agencia de Marketing Digital en México')

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        @include('components.hero')
        @include('components.partners')
        @include('components.services')
        @include('components.dashboard-cta')
        @include('components.testimonials')
        @include('components.pricing')
    </main>

    @include('components.footer')
@endsection
