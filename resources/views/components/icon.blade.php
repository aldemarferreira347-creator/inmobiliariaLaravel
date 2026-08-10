@props(['name'])

{!! \App\Soporte\Iconos::svg(
    $name,
    $attributes->get('class', 'h-5 w-5 shrink-0'),
    $attributes->except('class')->getAttributes(),
) !!}
