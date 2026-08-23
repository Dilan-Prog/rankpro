@extends('layouts.app')

@section('title', 'RankPro · Agencia de Marketing Digital en México')

@section('description', 'RankPro es la agencia de marketing digital en México experta en SEO, Google Ads y desarrollo web. Estrategias medibles que aumentan tus ventas y leads.')

@section('canonical', url('/'))

@section('content')
    @include('components.topbar')
    @include('components.navbar')

    <main>
        @include('components.hero')
        @include('components.partners')
        @include('components.services')
        @include('components.testimonials')
    </main>

    @include('components.footer')
@endsection
