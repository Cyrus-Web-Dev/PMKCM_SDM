<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Harian + Foto</title>
<style>
  @page { margin: 28px 32px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1b2026; }
  .bar { height: 6px; background: #e0590f; margin-bottom: 10px; }
  h1 { font-size: 16px; margin: 0 0 2px; text-transform: uppercase; letter-spacing: 1px; }
  .sub { color: #5d6672; margin-bottom: 12px; }
  .item { border: 1px solid #d3d7dc; border-left: 4px solid #e0590f; padding: 8px 10px; margin-bottom: 10px; page-break-inside: avoid; }
  .head { font-weight: bold; margin-bottom: 3px; }
  .meta { color: #5d6672; margin-bottom: 4px; }
  .issue { color: #b91c1c; }
  .photos img { width: 170px; margin: 6px 6px 0 0; border: 1px solid #ccc; }
</style>
</head>
<body>
  <div class="bar"></div>
  <h1>Laporan Harian + Foto Bukti</h1>
  <div class="sub">PMKCM V &middot; Indarung 5 &mdash; {{ $subtitle }}</div>
  @forelse ($items as $it)
    @php $r = $it['row']; @endphp
    <div class="item">
      <div class="head">{{ $r['employee'] }} &middot; {{ \Carbon\Carbon::parse($r['date'])->format('d/m/Y') }} &middot; {{ $r['status_label'] }}</div>
      <div class="meta">Tim {{ $r['team'] ?: '-' }} &middot; {{ $r['area'] }} &middot; {{ $r['start'] }}-{{ $r['end'] }} ({{ $r['hours'] }} jam) &middot; Review: {{ $r['review_label'] }}</div>
      <div>{{ $r['task'] }}</div>
      @if ($r['issue'])<div class="issue">Kendala: {{ $r['issue'] }}</div>@endif
      @if (count($it['images']))
        <div class="photos">@foreach ($it['images'] as $img)<img src="{{ $img }}" alt="foto">@endforeach</div>
      @endif
    </div>
  @empty
    <p style="color:#8c96a2">Tidak ada laporan pada periode ini.</p>
  @endforelse
  <div style="color:#8c96a2;font-size:8px">Dicetak {{ now()->format('d/m/Y H:i') }} &middot; maksimal 40 laporan per berkas &middot; SDM PMKCM V</div>
</body>
</html>
