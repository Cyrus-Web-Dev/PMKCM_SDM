<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { margin: 26px 28px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1b2026; }
  .bar { height: 7px; background: #e0590f; margin-bottom: 10px; }
  h1 { font-size: 17px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: 1px; }
  h2 { font-size: 13px; margin: 18px 0 6px; padding: 5px 8px; background: #1b2026; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
  h3 { font-size: 11px; margin: 12px 0 4px; color: #e0590f; }
  .meta { color: #5d6672; margin-bottom: 4px; }
  .note { border-left: 3px solid #e0590f; background: #f6f6f6; padding: 5px 8px; margin: 8px 0 4px; color: #333; }
  .t { font-weight: bold; margin: 10px 0 3px; font-size: 9.5px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  th { background: #e9ecef; text-align: left; padding: 3px 4px; font-size: 7.5px; text-transform: uppercase; border: 1px solid #c8cdd3; }
  td { padding: 3px 4px; border: 1px solid #d3d7dc; vertical-align: top; }
  tr:nth-child(even) td { background: #f7f8f9; }
  .empty { color: #8c96a2; font-style: italic; margin-bottom: 6px; }
  .foot { margin-top: 12px; color: #8c96a2; font-size: 7.5px; }
</style>
</head>
<body>
  <div class="bar"></div>
  <h1>{{ $title }}</h1>
  <div class="meta">PMKCM V &middot; Indarung 5 &mdash; dibuat {{ $generated_at }} oleh {{ $generated_by }}</div>
  <div class="note">Dokumen arsip baca-saja. Data sensitif (gaji, rekening, NPWP, BPJS, password) tidak disertakan. Untuk memulihkan data gunakan <b>Backup Database</b>, bukan dokumen ini.</div>

  @foreach ($sections as $section)
    <h2>{{ $section['title'] }}</h2>
    @foreach ($section['blocks'] as $b)
      @if ($b['type'] === 'heading')
        <h3>{{ $b['text'] }}</h3>
      @elseif ($b['type'] === 'text')
        <div class="empty">{{ $b['text'] }}</div>
      @else
        <div class="t">{{ $b['title'] }}</div>
        @if (count($b['rows']))
          <table>
            <thead><tr>@foreach ($b['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($b['rows'] as $r)<tr>@foreach ($r as $c)<td>{{ $c }}</td>@endforeach</tr>@endforeach</tbody>
          </table>
        @else
          <div class="empty">Tidak ada data.</div>
        @endif
      @endif
    @endforeach
  @endforeach

  <div class="foot">SDM PMKCM V &middot; {{ $generated_at }}</div>
</body>
</html>
