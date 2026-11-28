@extends('layouts.app')
@section('title','GEP Student Promotion')
@section('content')
@vite(['resources/css/pages/gep-promotion.css','resources/js/gepPromotion.js'])
@include('gep._promotion-content')
@endsection
