{{--
  Inline loader for widgets / cards / tables (opt-in, for genuinely async regions).
  Usage:
    <x-loader />                              simple centred orbit
    <x-loader :text="__('app.loading')" />   with caption
    <x-loader overlay />                      absolute overlay over a positioned parent
--}}
@props(['text' => null, 'overlay' => false, 'size' => '48px'])
<div {{ $attributes->merge(['class' => 'kh-loader' . ($overlay ? ' kh-loader--overlay' : '')]) }} role="status">
    <x-orbit :size="$size" :label="$text ?? __('app.loading')" />
    @if($text)
        <span class="kh-loader__text">{{ $text }}</span>
    @endif
</div>
