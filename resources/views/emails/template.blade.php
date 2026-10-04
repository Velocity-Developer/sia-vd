{{-- Surel sistem dari TemplateEmail::susun(). Berbeda dari bawaan Laravel: tanpa "Hello!"/"Regards," bila
     sapaan/penutup dikosongkan, kepala surel memakai nama institusi, dan teks bantuan tombol berbahasa Indonesia.
     Pindah baris di dalam paragraf dijadikan hard break Markdown (dua spasi di akhir baris). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $institusi }}
</x-mail::header>
</x-slot:header>

@if (filled($sapaan))
# {{ $sapaan }}
@endif

@foreach ($sebelum as $paragraf)
{{ preg_replace('/\R/', "  \n", $paragraf) }}

@endforeach
@if ($tombol)
<x-mail::button :url="$tautan">
{{ $tombol }}
</x-mail::button>

@endif
@foreach ($sesudah as $paragraf)
{{ preg_replace('/\R/', "  \n", $paragraf) }}

@endforeach
@if (filled($penutup))
{{ preg_replace('/\R/', "  \n", $penutup) }}
@endif

@if ($tombol)
<x-slot:subcopy>
Bila tombol "{{ $tombol }}" tidak bisa diklik, salin alamat berikut ke peramban: <span class="break-all">[{{ $tautan }}]({{ $tautan }})</span>
</x-slot:subcopy>
@endif

<x-slot:footer>
<x-mail::footer>
©{{ date('Y') }} {{ $institusi }}. All Rights Reserved. Design by [Velocity Developer](https://velocitydeveloper.com)
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
