{{--
  Shimmer placeholder for async regions.
  Usage:
    <x-skeleton variant="title" />
    <x-skeleton variant="line" :count="3" />
    <x-skeleton variant="block" height="180px" />
    <x-skeleton variant="circle" width="42px" />
--}}
@props(['variant' => 'text', 'width' => null, 'height' => null, 'count' => 1])
@for ($i = 0; $i < max(1, (int) $count); $i++)
    <div {{ $attributes->merge(['class' => 'kh-skeleton kh-skeleton--' . $variant]) }}
         @if($width || $height) style="@if($width)width: {{ $width }};@endif @if($height)height: {{ $height }};@endif" @endif
         aria-hidden="true"></div>
@endfor
