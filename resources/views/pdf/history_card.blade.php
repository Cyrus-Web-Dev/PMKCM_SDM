<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>History Card</title>
<style>
  @page { margin: 34px 40px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1b2026; }
  .bar { height: 7px; background: #e0590f; margin-bottom: 14px; }
  h1 { font-size: 17px; margin: 0; text-transform: uppercase; letter-spacing: 1.5px; }
  .sub { color: #5d6672; margin: 2px 0 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th, td { border: 1px solid #888; padding: 7px 9px; text-align: left; vertical-align: top; }
  th { background: #eee; width: 26%; }
  .sign td { border: 0; text-align: center; width: 50%; }
  .line { margin-top: 62px; border-top: 1px solid #1b2026; padding-top: 4px; font-weight: bold; }
</style>
</head>
<body>
  <div class="bar"></div>
  <h1>History Card</h1>
  <div class="sub">PMKCM V &middot; Indarung 5 &mdash; {{ $card['number'] }}</div>

  <table>
    <tr><th>Uraian Pekerjaan</th><td>{{ $card['description'] }}</td></tr>
    <tr><th>Tanggal Mulai</th><td>{{ \Carbon\Carbon::parse($card['start_date'])->format('d/m/Y') }}</td></tr>
    <tr><th>Tanggal Selesai</th><td>{{ \Carbon\Carbon::parse($card['end_date'])->format('d/m/Y') }}@if ($card['duration_days']) ({{ $card['duration_days'] }} hari)@endif</td></tr>
    <tr><th>Kepala Kerja</th><td>{{ $card['leader'] }}</td></tr>
    <tr><th>Anggota Kerja</th><td>@foreach ($card['members'] as $m){{ $loop->iteration }}. {{ $m['name'] }}<br>@endforeach</td></tr>
    <tr><th>Tim / Area</th><td>{{ $card['team'] ?: '-' }} / {{ $card['area'] ?: '-' }}</td></tr>
  </table>

  <table class="sign" style="margin-top:20px">
    <tr><td>Kepala Kerja<div class="line">{{ $card['leader'] }}</div></td><td>Ka. URS<div class="line">(.........................)</div></td></tr>
  </table>
  <div style="color:#8c96a2;font-size:8px">Dicetak {{ now()->format('d/m/Y H:i') }} &middot; SDM PMKCM V</div>
</body>
</html>
