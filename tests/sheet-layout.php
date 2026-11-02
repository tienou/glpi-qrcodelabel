<?php
require_once __DIR__ . '/../src/Sheetlayout.php';
use GlpiPlugin\Qrcodelabel\Sheetlayout;

function check($ok, string $message): void {
   if (!$ok) { throw new RuntimeException($message); }
}

// Independent measurements from Avery's U-1264-01 PDF, in points.
$tops = [38.25, 142.85, 247.5, 352.1, 456.75, 561.35, 666.0];
for ($i = 0; $i < 14; $i++) {
   [$x, $y] = Sheetlayout::position($i);
   check(abs($x * 72 / 25.4 - ($i % 2 ? 342 : 72)) < 0.001, 'Column alignment');
   check(abs($y * 72 / 25.4 - $tops[intdiv($i, 2)]) < 0.001, 'Row alignment');
   check($x >= 0 && $x + 69.85 <= 215.9 && $y + 31.75 <= 279.4, 'Within Letter page');
}
check(Sheetlayout::position(14) === Sheetlayout::position(0), 'Next page starts at top left');
check(Sheetlayout::sanitizeStart(-5) === 1, 'Clamp low start');
check(Sheetlayout::sanitizeStart(999) === 14, 'Clamp high start');
check(Sheetlayout::sanitizeStart([]) === 1, 'Reject array start');
check(Sheetlayout::sanitizeOffset('1.5') === 1.5, 'Fractional offset');
check(Sheetlayout::sanitizeOffset('bad') === 0.0, 'Reject invalid offset');
check(Sheetlayout::sanitizeOffset(INF) === 0.0, 'Reject infinite offset');
check(Sheetlayout::sanitizeOffset([]) === 0.0, 'Reject array offset');
[$x, $y] = Sheetlayout::position(13, 99, -99);
[$baseX, $baseY] = Sheetlayout::position(13);
check(abs($x - $baseX - 5) < 0.001 && abs($y - $baseY + 5) < 0.001, 'Bounded calibration');
echo "Sheet layout checks passed.\n";
