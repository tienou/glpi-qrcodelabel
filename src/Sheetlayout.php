<?php

/* QR Code Label — AGPL-3.0-or-later. */
namespace GlpiPlugin\Qrcodelabel;

/** Avery 61528 / U-1264-01 sheet coordinates, in millimetres. */
final class Sheetlayout {
   public const CAPACITY = 14;

   public static function sanitizeStart($value): int {
      return is_scalar($value) ? max(1, min(self::CAPACITY, (int)$value)) : 1;
   }

   public static function sanitizeOffset($value): float {
      if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value)) {
         return 0.0;
      }
      return max(-5.0, min(5.0, (float)$value));
   }

   /**
    * Coordinates measured from Avery's downloadable PDF U-1264-01.
    * The PDF is shared by 61528 and 94284; its actual path coordinates
    * take precedence over the rounded margins on the product page.
    * Source: https://www.avery.com/templates/presta-61528
    * Read left to right, then top to bottom. Offsets apply to every page.
    */
   public static function position(int $slot, $offsetX = 0, $offsetY = 0): array {
      $slot = max(0, $slot) % self::CAPACITY;
      $rowTops = [38.25, 142.85, 247.5, 352.1, 456.75, 561.35, 666.0];
      $ptToMm = 25.4 / 72;
      return [
         (72 + ($slot % 2) * 270) * $ptToMm + self::sanitizeOffset($offsetX),
         $rowTops[intdiv($slot, 2)] * $ptToMm + self::sanitizeOffset($offsetY),
      ];
   }
}
