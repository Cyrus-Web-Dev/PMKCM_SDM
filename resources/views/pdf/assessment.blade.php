<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Form Penilaian</title>
<style>
  @page { margin: 34px 40px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1b2026; }
  .bar { height: 7px; background: #e0590f; margin-bottom: 14px; }
  h1 { font-size: 17px; margin: 0; text-transform: uppercase; letter-spacing: 1.5px; }
  .sub { color: #5d6672; margin: 2px 0 16px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th, td { border: 1px solid #888; padding: 7px 9px; text-align: left; }
  th { background: #eee; }
  .info td { border: 0; padding: 3px 0; }
  .total td { font-weight: bold; background: #f6f6f6; }
  .sign td { border: 0; text-align: center; width: 33%; padding-top: 6px; }
  .line { margin-top: 62px; border-top: 1px solid #1b2026; padding-top: 4px; font-weight: bold; }
</style>
</head>
<body>
  <div class="bar"></div>
  <h1>Form Penilaian Outsourcing</h1>
  <div class="sub">PMKCM V &middot; Indarung 5 &mdash; Periode {{ $period_label }}</div>

  <table class="info">
    <tr><td width="22%">Nama</td><td>: {{ $employee['full_name'] }}</td></tr>
    <tr><td>NIP</td><td>: {{ $employee['nip'] }}</td></tr>
    <tr><td>Jabatan</td><td>: {{ $employee['position'] ?: '-' }}</td></tr>
    <tr><td>Tim</td><td>: {{ $employee['team'] ?: '-' }}</td></tr>
  </table>

  <table>
    <tr><th style="width:8%">No</th><th>Kriteria</th><th style="width:20%">Nilai (0-100)</th></tr>
    @foreach ($criteria as $i => $c)
      <tr><td>{{ $i + 1 }}</td><td>{{ $c['label'] }}</td><td>{{ $assessment['scores'][$c['key']] }}</td></tr>
    @endforeach
    <tr class="total"><td colspan="2">Nilai Akhir (rata-rata)</td><td>{{ $assessment['total'] }}</td></tr>
    <tr class="total"><td colspan="2">Predikat</td><td>{{ $assessment['grade'] }}</td></tr>
  </table>

  <table>
    <tr><th colspan="2">Ringkasan Laporan Harian Periode Ini</th></tr>
    <tr><td style="width:60%">Jumlah laporan</td><td>{{ $stat['reports'] }}</td></tr>
    <tr><td>Pekerjaan selesai</td><td>{{ $stat['done'] }}</td></tr>
    <tr><td>Total jam kerja</td><td>{{ $stat['hours'] }} jam</td></tr>
  </table>

  <p><b>Catatan penilai:</b><br>{{ $assessment['note'] ?: '-' }}</p>

  <table class="sign" style="margin-top:20px">
    <tr><td>Karyawan<div class="line">{{ $employee['full_name'] }}</div></td>
        <td>Ka. URS<div class="line">{{ $assessor ?: '(.........................)' }}</div></td>
        <td>Owner<div class="line">{{ $owner ?: '(.........................)' }}</div></td></tr>
  </table>
</body>
</html>
