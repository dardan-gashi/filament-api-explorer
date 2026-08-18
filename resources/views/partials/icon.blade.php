{{-- An icon from the panel's own set.

     The width and height are attributes and not only CSS on purpose: a heroicon
     carries nothing but a viewBox, so an unsized one grows to fill whatever box
     it lands in — which is exactly what a host application with a stale copy of
     this package's stylesheet looks like. --}}
@svg($name, $class ?? 'fae-icon', array_merge(['width' => 14, 'height' => 14], $extra ?? []))
