<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page { margin: 26px 28px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1b2026; }
  .bar { height: 6px; background: #e0590f; margin-bottom: 10px; }
  h1 { font-size: 16px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: 1px; }
  .sub { color: #5d6672; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1b2026; color: #fff; text-align: left; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; }
  td { border-bottom: 1px solid #d3d7dc; padding: 5px 6px; vertical-align: top; }
  tr:nth-child(even) td { background: #f3f4f6; }
  .foot { margin-top: 10px; color: #8c96a2; font-size: 8px; }
</style>
</head>
<body>
  <div class="bar"></div>
  <h1>{{ $title }}</h1>
  <div class="sub">PMKCM V &middot; Indarung 5 &mdash; {{ $subtitle }}</div>
  <table>
    <thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
      @forelse ($rows as $r)
        <tr>@foreach ($r as $c)<td>{{ $c }}</td>@endforeach</tr>
      @empty
        <tr><td colspan="{{ count($headings) }}" style="text-align:center;color:#8c96a2">Tidak ada data.</td></tr>
      @endforelse
    </tbody>
  </table>
  <div class="foot">Dicetak {{ now()->format('d/m/Y H:i') }} &middot; SDM PMKCM V</div>
</body>
</html>
