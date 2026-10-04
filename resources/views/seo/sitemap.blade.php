{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($halaman as $h)
    <url>
        <loc>{{ $h['loc'] }}</loc>
@if ($h['lastmod'])
        <lastmod>{{ $h['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $h['changefreq'] }}</changefreq>
        <priority>{{ $h['priority'] }}</priority>
    </url>
@endforeach
</urlset>
