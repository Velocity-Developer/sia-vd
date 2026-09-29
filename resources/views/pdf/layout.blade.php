{{--
    Kerangka bersama semua dokumen PDF: kop resmi, gaya tabel, dan kaki halaman bernomor.
    Variabel wajib: $institusi, $logoSrc, $kontak. Opsional: $unitKop (baris fakultas/prodi di kop).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>@yield('judul-berkas')</title>
    <style>
        @page { margin: 1.4cm 1.7cm 1.9cm 1.7cm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Serif', serif; font-size: 9.5pt; line-height: 1.35; color: #000; margin: 0; }
        p { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }

        /* Kop surat */
        .kop td { vertical-align: middle; padding: 0; }
        .kop-logo { width: 78px; }
        .kop-logo img { max-width: 70px; max-height: 70px; }
        .kop-teks, .kop p { text-align: center; }
        .kop-seimbang { width: 78px; }
        .kop-nama { font-size: 14.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.6px; margin: 0; line-height: 1.2; }
        .kop-unit { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 2px 0 0; }
        .kop-kontak { font-size: 8pt; margin: 3px 0 0; }
        .garis-kop { border-top: 2.4px solid #000; margin-top: 7px; }
        .garis-kop-tipis { border-top: 0.8px solid #000; margin: 1.6px 0 12px; }

        /* Judul dokumen */
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-decoration: underline; margin: 0; }
        .subjudul { text-align: center; font-size: 9.5pt; margin: 3px 0 12px; }

        /* Identitas (label : isi) */
        .identitas { margin-bottom: 10px; }
        .identitas td { padding: 1.5px 0; vertical-align: top; }
        .identitas .label { width: 128px; white-space: nowrap; }
        .identitas .titik { width: 10px; }

        /* Tabel data */
        .grid { font-size: 8.8pt; }
        .grid th, .grid td { border: 0.7px solid #000; padding: 4px 5px; vertical-align: top; }
        .grid th { background: #e6e6e6; font-weight: bold; text-align: center; vertical-align: middle; font-size: 8.3pt; text-transform: uppercase; }
        .grid tfoot td, .grid .jumlah td { font-weight: bold; background: #f2f2f2; }
        .grid tr { page-break-inside: avoid; }
        .tengah { text-align: center; }
        .nowrap { white-space: nowrap; }
        .kanan { text-align: right; }
        .tebal { font-weight: bold; }
        .kecil { font-size: 7.6pt; color: #333; }
        .merah { color: #9b1c1c; font-weight: bold; }
        .kosong { text-align: center; padding: 16px; font-style: italic; }

        /* Ringkasan & catatan */
        .ringkasan { margin-top: 8px; width: 60%; }
        .ringkasan td { padding: 1.5px 0; }
        .ringkasan .label { width: 210px; }
        .catatan { margin-top: 8px; font-size: 8pt; }
        .catatan ol { margin: 2px 0 0; padding-left: 16px; }
        .catatan-judul { font-weight: bold; }

        /* Tanda tangan */
        .ttd { margin-top: 22px; page-break-inside: avoid; }
        .ttd td { text-align: center; vertical-align: top; padding: 0 6px; }
        .ttd .ruang { height: 58px; }
        .ttd .nama { font-weight: bold; text-decoration: underline; }
        .ttd-tunggal { width: 44%; margin-left: 56%; text-align: center; margin-top: 22px; page-break-inside: avoid; }
        .ttd-tunggal .ruang { height: 58px; }

        /* Kaki halaman */
        .kaki { position: fixed; bottom: -1.15cm; left: 0; right: 0; font-size: 7pt; color: #444; border-top: 0.5px solid #888; padding-top: 3px; }
        .kaki td { padding: 0; }
        .nomor-halaman:after { content: counter(page); }
        .halaman-baru { page-break-before: always; }
        @yield('gaya')
    </style>
</head>
<body>
    <div class="kaki">
        <table>
            <tr>
                <td>Dicetak melalui Sistem Informasi Akademik {{ $institusi->nama_pt }} pada {{ now()->translatedFormat('d F Y, H.i') }} {{ \App\Models\PengaturanInstitusi::singkatanZona() }}</td>
                <td class="kanan">Halaman <span class="nomor-halaman"></span></td>
            </tr>
        </table>
    </div>

    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo {{ $institusi->nama_pt }}">
                @endif
            </td>
            <td class="kop-teks">
                <p class="kop-nama">{{ $institusi->nama_pt }}</p>
                @if (! empty($unitKop))
                    <p class="kop-unit">{{ $unitKop }}</p>
                @endif
                @if ($kontak)
                    <p class="kop-kontak">{{ implode(' · ', $kontak) }}</p>
                @endif
            </td>
            <td class="kop-seimbang"></td>
        </tr>
    </table>
    <div class="garis-kop"></div>
    <div class="garis-kop-tipis"></div>

    @yield('isi')
</body>
</html>
