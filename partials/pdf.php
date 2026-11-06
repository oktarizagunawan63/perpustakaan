<?php
/**
 * Penulis PDF kecil tanpa pustaka luar: A4 mendatar dengan font Helvetica bawaan PDF.
 * shortcut: hanya aksara Latin (CP1252); pakai pustaka PDF dengan font tertanam bila perlu aksara lain.
 */
final class Pdf
{
    private const W = 842, H = 595, TEPI = 30;
    private const HIJAU = '0.059 0.239 0.208', KRIM = '0.937 0.914 0.867', TINTA = '0.1 0.14 0.13', REDUP = '0.42 0.45 0.43', GARIS = '0.84 0.81 0.75', PUTIH = '1 1 1';
    /** Lebar huruf Helvetica (1/1000 em) untuk karakter 32–126. */
    private const LEBAR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556,
        278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944,
        667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556,
        500, 722, 500, 500, 500, 334, 260, 334, 584];

    /** @var list<string> perintah gambar tiap halaman */
    private array $halaman = [];
    private ?int $sasaran = null; // halaman yang sedang digambar; null = halaman terakhir
    private float $y = 0;

    public function __construct(private string $judul, private string $kaki)
    {
        $this->halamanBaru();
    }

    private function halamanBaru(): void
    {
        $this->halaman[] = '';
        $this->y = self::H - self::TEPI;
    }

    private function gambar(string $perintah): void
    {
        $this->halaman[$this->sasaran ?? array_key_last($this->halaman)] .= $perintah . "\n";
    }

    private function angka(float ...$n): string
    {
        return implode(' ', array_map(fn($v) => sprintf('%.2F', $v), $n));
    }

    private function cp1252(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    private function lolos(string $s): string
    {
        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ']);
    }

    /** Lebar teks CP1252 dalam poin; huruf tebal sedikit lebih lebar. */
    private function ukur(string $s, float $ukuran, bool $tebal): float
    {
        $total = 0;
        foreach (str_split($s) as $c) $total += self::LEBAR[ord($c) - 32] ?? 556;
        return $total * $ukuran / 1000 * ($tebal ? 1.06 : 1);
    }

    private function teks(float $x, float $y, string $isi, float $ukuran = 8.5, bool $tebal = false, string $warna = self::TINTA, string $rata = 'L', float $maks = 0): void
    {
        $s = $this->cp1252($isi);
        if ($s === '') return;
        if ($maks && $this->ukur($s, $ukuran, $tebal) > $maks) {
            while ($s !== '' && $this->ukur($s . "\x85", $ukuran, $tebal) > $maks) $s = substr($s, 0, -1);
            $s = rtrim($s) . "\x85"; // elipsis
        }
        if ($rata === 'R') $x -= $this->ukur($s, $ukuran, $tebal);
        $this->gambar("$warna rg BT /F" . ($tebal ? 2 : 1) . " {$this->angka($ukuran)} Tf {$this->angka($x, $y)} Td ({$this->lolos($s)}) Tj ET");
    }

    private function kotak(float $x, float $y, float $w, float $h, string $warna): void
    {
        $this->gambar("$warna rg {$this->angka($x, $y, $w, $h)} re f");
    }

    private function garis(float $y): void
    {
        $this->gambar(self::GARIS . " RG 0.5 w {$this->angka(self::TEPI, $y)} m {$this->angka(self::W - self::TEPI, $y)} l S");
    }

    /** Pindah halaman bila sisa ruang (di atas kaki halaman) kurang dari $tinggi. */
    private function sisakan(float $tinggi): bool
    {
        if ($this->y - $tinggi >= self::TEPI + 20) return false;
        $this->halamanBaru();
        return true;
    }

    public function kepala(string $judul, string $sub, string $kanan): void
    {
        $this->kotak(0, self::H - 72, self::W, 72, self::HIJAU);
        $this->teks(self::TEPI, self::H - 36, $judul, 18, true, self::PUTIH);
        $this->teks(self::TEPI, self::H - 54, $sub, 9.5, false, self::KRIM);
        $this->teks(self::W - self::TEPI, self::H - 54, $kanan, 8.5, false, self::KRIM, 'R');
        $this->y = self::H - 72 - 18;
    }

    /** @param list<array{0: string, 1: string, 2: string}> $isi [label, nilai, keterangan] */
    public function kartu(array $isi): void
    {
        $jarak = 10;
        $w = (self::W - 2 * self::TEPI - $jarak * (count($isi) - 1)) / count($isi);
        $h = 56;
        foreach ($isi as $i => [$label, $nilai, $ket]) {
            $x = self::TEPI + $i * ($w + $jarak);
            $this->kotak($x, $this->y - $h, $w, $h, self::KRIM);
            $this->teks($x + 10, $this->y - 15, strtoupper($label), 7, true, self::REDUP);
            $this->teks($x + 10, $this->y - 33, $nilai, 15, true);
            $this->teks($x + 10, $this->y - 47, $ket, 7.5, false, self::REDUP, 'L', $w - 20);
        }
        $this->y -= $h + 20;
    }

    public function bagian(string $judul): void
    {
        $this->sisakan(52);
        $this->teks(self::TEPI, $this->y, $judul, 11, true);
        $this->y -= 10;
    }

    public function catatan(string $isi): void
    {
        $this->teks(self::TEPI, $this->y - 8, $isi, 9, false, self::REDUP);
        $this->y -= 26;
    }

    /**
     * Tabel dengan kepala kolom yang diulang di tiap halaman; teks panjang dipangkas dengan elipsis.
     * @param list<array{0: string, 1: float, 2?: string}> $kolom [judul, lebar, 'R' rata kanan | 'B' batang (nilai 0–1)]
     * @param list<list<string|float>> $baris
     */
    public function tabel(array $kolom, array $baris): void
    {
        $kolom = array_map(fn($k) => $k + [2 => 'L'], $kolom);
        $kepala = function () use ($kolom): void {
            $x = self::TEPI;
            foreach ($kolom as [$judul, $lebar, $jenis]) {
                $kanan = $jenis === 'R';
                $this->teks($kanan ? $x + $lebar - 12 : $x, $this->y - 10, strtoupper($judul), 7, true, self::REDUP, $kanan ? 'R' : 'L');
                $x += $lebar;
            }
            $this->y -= 15;
            $this->garis($this->y);
        };
        $this->sisakan(34);
        $kepala();
        foreach ($baris as $b) {
            if ($this->sisakan(16)) $kepala();
            $x = self::TEPI;
            foreach ($kolom as $i => [, $lebar, $jenis]) {
                if ($jenis === 'B') {
                    if ($b[$i] > 0) $this->kotak($x, $this->y - 11, max(2, ($lebar - 10) * $b[$i]), 6, self::HIJAU);
                } else {
                    $kanan = $jenis === 'R';
                    $this->teks($kanan ? $x + $lebar - 12 : $x, $this->y - 11, (string) $b[$i], 8.5, false, self::TINTA, $kanan ? 'R' : 'L', $lebar - ($kanan ? 12 : 5));
                }
                $x += $lebar;
            }
            $this->y -= 16;
            $this->garis($this->y);
        }
        $this->y -= 20;
    }

    /** Susun file PDF utuh, lengkap dengan kaki dan nomor di tiap halaman. */
    public function selesai(): string
    {
        $jumlah = count($this->halaman);
        foreach (array_keys($this->halaman) as $i) {
            $this->sasaran = $i;
            $this->teks(self::TEPI, 22, $this->kaki, 7.5, false, self::REDUP);
            $this->teks(self::W - self::TEPI, 22, 'Halaman ' . ($i + 1) . ' dari ' . $jumlah, 7.5, false, self::REDUP, 'R');
        }

        $font = fn(string $nama) => "<< /Type /Font /Subtype /Type1 /BaseFont /$nama /Encoding /WinAnsiEncoding >>";
        $anak = implode(' ', array_map(fn($i) => (6 + 2 * $i) . ' 0 R', array_keys($this->halaman)));
        $objek = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => "<< /Type /Pages /Kids [$anak] /Count $jumlah >>",
            3 => $font('Helvetica'),
            4 => $font('Helvetica-Bold'),
            5 => '<< /Title (' . $this->lolos($this->cp1252($this->judul)) . ') /Producer (' . $this->lolos($this->cp1252(NAMA_PERPUS)) . ') /CreationDate (D:' . date('YmdHis') . "+07'00') >>",
        ];
        foreach ($this->halaman as $i => $isi) {
            $data = gzcompress($isi);
            $objek[6 + 2 * $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . (7 + 2 * $i) . ' 0 R >>';
            $objek[7 + 2 * $i] = '<< /Length ' . strlen($data) . " /Filter /FlateDecode >>\nstream\n$data\nendstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posisi = [];
        foreach ($objek as $n => $isi) {
            $posisi[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$isi\nendobj\n";
        }
        $xref = strlen($pdf);
        $total = count($objek) + 1;
        $pdf .= "xref\n0 $total\n0000000000 65535 f \n";
        foreach ($posisi as $p) $pdf .= sprintf("%010d 00000 n \n", $p);
        return $pdf . "trailer\n<< /Size $total /Root 1 0 R /Info 5 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }
}
