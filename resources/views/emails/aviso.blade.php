@component('emails.layout', ['titulo' => $titulo, 'accion' => $accion])
    @foreach ($parrafos as $parrafo)
        <p style="margin:0 0 14px 0;font-size:15px;line-height:1.6;color:#334155;">{{ $parrafo }}</p>
    @endforeach
@endcomponent
