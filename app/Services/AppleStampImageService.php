<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;

/**
 * Generates a strip image (750×246 @2x) embedded into Apple Wallet .pkpass files.
 *
 * Unlike Google Wallet, images are bundled locally inside the .pkpass ZIP —
 * no public URL or internet access required.
 *
 * Strip dimensions (Apple StoreCard):
 *   @1x  375×123 px
 *   @2x  750×246 px  ← rendered here, then downsampled
 */
class AppleStampImageService
{
    private const W = 750;
    private const H = 246;
    private const SCALE = 2;  // super-sample for anti-aliasing
    private const ROWS = 3;   // fixed 3-row layout
    private const COLS = 5;   // fixed 5-column layout

    /** @var array<string, \GdImage|null> */
    private array $assetCache = [];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate (or retrieve cached) strip images.
     * Returns ['x1' => path, 'x2' => path] with local file paths.
     */
    public function pathsFor(LoyaltyCard $card): array
    {
        $x2 = $this->storagePath($card, '2x');
        $x1 = $this->storagePath($card, '1x');

        if (! file_exists($x2)) {
            $this->generate($card, $x2, self::W, self::H);
            $this->generate($card, $x1, self::W / 2, self::H / 2);
        }

        return ['x1' => $x1, 'x2' => $x2];
    }

    public function regenerateFor(LoyaltyCard $card): array
    {
        $x2 = $this->storagePath($card, '2x');
        $x1 = $this->storagePath($card, '1x');

        $this->generate($card, $x2, self::W, self::H);
        $this->generate($card, $x1, self::W / 2, self::H / 2);

        return ['x1' => $x1, 'x2' => $x2];
    }

    public function clearFor(LoyaltyCard $card): void
    {
        $dir     = $this->storageDir();
        $pattern = "{$dir}/apple_strip_{$card->loyalty_program_id}_*.png";

        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }

    // ── Core renderer ─────────────────────────────────────────────────────────

    private function generate(LoyaltyCard $card, string $outputPath, int $outW, int $outH): void
    {
        $program  = $card->loyaltyProgram;
        $business = $program->business;
        $total    = $program->total_stamps;
        $filled   = min($card->stamps_collected, $total);

        $program->loadMissing('milestones');
        $milestoneCounts = array_flip($program->milestoneCounts());

        $style = $program->stamp_style ?? 'minimal';
        $scale = max(0.5, min(1.5, (float) ($program->stamp_scale ?? 1.0)));
        $font  = $program->fontPath();

        [$bgR, $bgG, $bgB] = $this->hexToRgb($business->primary_color ?? '#1a1a2e');
        [$fgR, $fgG, $fgB] = $this->hexToRgb($business->secondary_color ?? '#ffffff');

        $rW = $outW * self::SCALE;
        $rH = $outH * self::SCALE;

        $canvas = imagecreatetruecolor($rW, $rH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imageantialias($canvas, true);

        // Background
        $this->drawBackground($canvas, $rW, $rH, $style, $bgR, $bgG, $bgB);

        $rows   = self::ROWS;   // 3 filas fijas
        $perRow = self::COLS;   // 5 columnas fijas → 15 posiciones siempre

        // Wide zone so the grid fills the canvas; tall enough for medium-large stamps
        $availW = (int) ($rW * 0.92);
        $availH = (int) ($rH * 0.74);

        // Vertical gap: proportional to height (binding constraint on a wide canvas)
        $gapY = (int) max(4 * self::SCALE, (int) ($rH * 0.030));

        // Stamp diameter from height — height is always the bottleneck (3:1 canvas)
        $maxByHeight = (int) floor(($availH - ($rows - 1) * $gapY) / $rows);
        $stampD      = (int) ($maxByHeight * 0.84);
        $stampD      = (int) ($stampD * $scale);

        // Horizontal gap: calculated to spread 5 stamps evenly across the available width
        $gapX = max($gapY, (int) floor(($availW - $perRow * $stampD) / ($perRow - 1)));

        // Fixed 5×3 grid dimensions (independent of $total — always 15 positions)
        $totalGridW = $perRow * $stampD + ($perRow - 1) * $gapX;
        $totalGridH = $rows   * $stampD + ($rows   - 1) * $gapY;

        // Centre grid in the full canvas (Apple Wallet has no progress strip)
        $startX = (int) (($rW - $totalGridW) / 2);
        $startY = (int) (($rH - $totalGridH) / 2);

        $ctx = [
            'style'       => $style,
            'fgR' => $fgR, 'fgG' => $fgG, 'fgB' => $fgB,
            'bgR' => $bgR, 'bgG' => $bgG, 'bgB' => $bgB,
            'font'        => $font,
            'filledAsset' => $program->filled_stamp_image,
            'emptyAsset'  => $program->empty_stamp_image,
            'badgeAsset'  => $program->reward_badge_image,
        ];

        $stampN = 0;
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $perRow; $col++, $stampN++) {
                $cx          = $startX + $col * ($stampD + $gapX) + (int) ($stampD / 2);
                $cy          = $startY + $row * ($stampD + $gapY) + (int) ($stampD / 2);
                $stampNumber = $stampN + 1;
                $isMilestone = isset($milestoneCounts[$stampNumber]);
                $isFinal     = ($stampNumber === $total);

                if ($stampN < $filled) {
                    $this->renderFilledStamp($canvas, $cx, $cy, $stampD, $ctx);
                } else {
                    $this->renderEmptyStamp($canvas, $cx, $cy, $stampD, $ctx);
                }

                if ($isMilestone || $isFinal) {
                    $this->renderMilestoneBadge($canvas, $cx, $cy, $stampD, $ctx, $isFinal);
                }
            }
        }

        // Downsample to output size
        $output = imagecreatetruecolor($outW, $outH);
        imagesavealpha($output, true);
        imagealphablending($output, false);
        imagecopyresampled($output, $canvas, 0, 0, 0, 0, $outW, $outH, $rW, $rH);
        imagedestroy($canvas);
        $this->freeAssetCache();

        is_dir(dirname($outputPath)) || mkdir(dirname($outputPath), 0755, true);
        imagepng($output, $outputPath, 9);
        imagedestroy($output);
    }

    // ── Stamp dispatchers ─────────────────────────────────────────────────────

    private function renderFilledStamp(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        if ($ctx['filledAsset'] && ($asset = $this->loadAsset($ctx['filledAsset']))) {
            $this->pasteAsset($c, $asset, $cx, $cy, $d);
            return;
        }

        match ($ctx['style']) {
            'luxury' => $this->drawLuxuryFilled($c, $cx, $cy, $d),
            'neon'   => $this->drawNeonFilled($c, $cx, $cy, $d, $ctx),
            'coffee' => $this->drawCoffeeFilled($c, $cx, $cy, $d),
            'retro'  => $this->drawRetroFilled($c, $cx, $cy, $d),
            default  => $this->drawMinimalFilled($c, $cx, $cy, $d, $ctx),
        };
    }

    private function renderEmptyStamp(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        if ($ctx['emptyAsset'] && ($asset = $this->loadAsset($ctx['emptyAsset']))) {
            $this->pasteAsset($c, $asset, $cx, $cy, $d);
            return;
        }

        match ($ctx['style']) {
            'luxury' => $this->drawLuxuryEmpty($c, $cx, $cy, $d),
            'neon'   => $this->drawNeonEmpty($c, $cx, $cy, $d, $ctx),
            'coffee' => $this->drawCoffeeEmpty($c, $cx, $cy, $d),
            'retro'  => $this->drawRetroEmpty($c, $cx, $cy, $d),
            default  => $this->drawMinimalEmpty($c, $cx, $cy, $d, $ctx),
        };
    }

    private function renderMilestoneBadge(\GdImage $c, int $cx, int $cy, int $d, array $ctx, bool $isFinal): void
    {
        $badgeD = (int) ($d * 0.38);
        $r      = (int) ($d / 2);
        $bx     = $cx + (int) ($r * 0.64);
        $by     = $cy - (int) ($r * 0.64);

        if ($ctx['badgeAsset'] && ($asset = $this->loadAsset($ctx['badgeAsset']))) {
            $this->pasteAsset($c, $asset, $bx, $by, $badgeD);
            return;
        }

        $br     = (int) ($badgeD / 2);
        $shadow = imagecolorallocatealpha($c, 0, 0, 0, 85);
        imagefilledellipse($c, $bx + 2, $by + 2, $badgeD + 4, $badgeD + 4, $shadow);

        [$fillR, $fillG, $fillB]       = $isFinal ? [255, 210, 40] : [60, 200, 110];
        [$outlineR, $outlineG, $outlineB] = $isFinal ? [170, 125, 5] : [25, 130, 65];

        imagefilledellipse($c, $bx, $by, $badgeD + 3, $badgeD + 3, imagecolorallocate($c, $outlineR, $outlineG, $outlineB));
        imagefilledellipse($c, $bx, $by, $badgeD, $badgeD, imagecolorallocate($c, $fillR, $fillG, $fillB));
        imagefilledellipse($c, $bx - (int)($br * 0.2), $by - (int)($br * 0.2), (int)($badgeD * 0.55), (int)($badgeD * 0.55), imagecolorallocatealpha($c, 255, 255, 255, 60));

        $font = $ctx['font'];
        if (file_exists($font)) {
            $sz   = (int) ($br * 0.85);
            $bbox = imagettfbbox($sz, 0, $font, '*');
            $tw   = abs($bbox[4] - $bbox[0]);
            $th   = abs($bbox[5] - $bbox[1]);
            imagettftext($c, $sz, 0, (int)($bx - $tw / 2), (int)($by + $th / 2), imagecolorallocate($c, 50, 25, 0), $font, '*');
        }
    }

    // ── Themes ────────────────────────────────────────────────────────────────

    private function drawMinimalFilled(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];
        for ($i = 4; $i >= 1; $i--) {
            imagefilledellipse($c, $cx, $cy, $d + $i * 5, $d + $i * 5, imagecolorallocatealpha($c, $r, $g, $b, 118 - $i * 3));
        }
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocate($c, $r, $g, $b));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.32), (int)($d * 0.32), imagecolorallocate($c, $ctx['bgR'], $ctx['bgG'], $ctx['bgB']));
    }

    private function drawMinimalEmpty(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocatealpha($c, $r, $g, $b, 105));
        $thick = max(2, (int)($d * 0.055));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, imagecolorallocatealpha($c, $r, $g, $b, 70));
        }
    }

    private function drawLuxuryFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        for ($i = 6; $i >= 0; $i--) {
            imagefilledellipse($c, $cx, $cy, $d + $i * 4, $d + $i * 4, imagecolorallocatealpha($c, 212, 175, 55, 112 - $i * 3));
        }
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocate($c, 130, 100, 20));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.90), (int)($d * 0.90), imagecolorallocate($c, 212, 175, 55));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.72), (int)($d * 0.72), imagecolorallocate($c, 245, 215, 100));
        imagefilledellipse($c, $cx - (int)($d * 0.12), $cy - (int)($d * 0.12), (int)($d * 0.42), (int)($d * 0.42), imagecolorallocatealpha($c, 255, 255, 255, 65));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.24), (int)($d * 0.24), imagecolorallocate($c, 130, 100, 20));
    }

    private function drawLuxuryEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocatealpha($c, 212, 175, 55, 108));
        $thick = max(3, (int)($d * 0.07));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, imagecolorallocatealpha($c, 212, 175, 55, 65));
        }
    }

    private function drawNeonFilled(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];
        for ($i = 10; $i >= 0; $i--) {
            imagefilledellipse($c, $cx, $cy, $d + $i * 5, $d + $i * 5, imagecolorallocatealpha($c, $r, $g, $b, 105 + $i * 2));
        }
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocate($c, min(255, $r + 90), min(255, $g + 90), min(255, $b + 90)));
        imagefilledellipse($c, $cx - (int)($d * 0.14), $cy - (int)($d * 0.14), (int)($d * 0.48), (int)($d * 0.48), imagecolorallocatealpha($c, 255, 255, 255, 22));
    }

    private function drawNeonEmpty(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocatealpha($c, $r, $g, $b, 108));
        $thick = max(2, (int)($d * 0.05));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, imagecolorallocatealpha($c, $r, $g, $b, 68));
        }
    }

    private function drawCoffeeFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        imagefilledellipse($c, $cx + 3, $cy + 3, $d, $d, imagecolorallocatealpha($c, 0, 0, 0, 88));
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocate($c, 55, 25, 8));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.88), (int)($d * 0.88), imagecolorallocate($c, 110, 55, 18));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.70), (int)($d * 0.70), imagecolorallocate($c, 195, 135, 75));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.38), (int)($d * 0.38), imagecolorallocate($c, 245, 220, 180));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.14), (int)($d * 0.14), imagecolorallocate($c, 55, 25, 8));
    }

    private function drawCoffeeEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocatealpha($c, 170, 110, 50, 105));
        $thick = max(2, (int)($d * 0.06));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, imagecolorallocatealpha($c, 170, 110, 50, 72));
        }
    }

    private function drawRetroFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $inner = (int)($d * 0.82);
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocate($c, 55, 38, 18));
        imagefilledellipse($c, $cx, $cy, $inner, $inner, imagecolorallocate($c, 175, 148, 95));
        for ($t = 0; $t < 3; $t++) {
            imageellipse($c, $cx, $cy, $inner - $t * 4, $inner - $t * 4, imagecolorallocatealpha($c, 55, 38, 18, 85));
        }
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.48), (int)($d * 0.48), imagecolorallocate($c, 55, 38, 18));
        imagefilledellipse($c, $cx, $cy, (int)($d * 0.32), (int)($d * 0.32), imagecolorallocate($c, 110, 82, 42));
    }

    private function drawRetroEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        imagefilledellipse($c, $cx, $cy, $d, $d, imagecolorallocatealpha($c, 180, 155, 100, 108));
        $thick = max(2, (int)($d * 0.06));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, imagecolorallocatealpha($c, 100, 78, 40, 72));
        }
    }

    // ── Background ────────────────────────────────────────────────────────────

    private function drawBackground(\GdImage $c, int $w, int $h, string $style, int $bgR, int $bgG, int $bgB): void
    {
        match ($style) {
            'luxury' => $this->drawGradient($c, $w, $h, [8, 6, 4], [28, 20, 8]),
            'neon'   => $this->drawGradient($c, $w, $h, [4, 4, 14], [12, 5, 22]),
            'coffee' => $this->drawGradient($c, $w, $h, [38, 20, 8], [60, 32, 12]),
            'retro'  => $this->drawGradient($c, $w, $h, [52, 42, 25], [78, 62, 38]),
            default  => $this->drawSolidBg($c, $w, $h, $bgR, $bgG, $bgB),
        };
    }

    private function drawSolidBg(\GdImage $c, int $w, int $h, int $r, int $g, int $b): void
    {
        imagefilledrectangle($c, 0, 0, $w, $h, imagecolorallocate($c, $r, $g, $b));
        $this->drawVignette($c, $w, $h);
    }

    private function drawGradient(\GdImage $c, int $w, int $h, array $from, array $to): void
    {
        for ($y = 0; $y < $h; $y++) {
            $t   = $y / $h;
            $col = imagecolorallocate($c,
                (int)($from[0] + ($to[0] - $from[0]) * $t),
                (int)($from[1] + ($to[1] - $from[1]) * $t),
                (int)($from[2] + ($to[2] - $from[2]) * $t),
            );
            imagefilledrectangle($c, 0, $y, $w, $y + 1, $col);
        }
        $this->drawVignette($c, $w, $h);
    }

    private function drawVignette(\GdImage $c, int $w, int $h): void
    {
        for ($i = 0; $i < 8; $i++) {
            imagerectangle($c, $i * 2, $i * 2, $w - $i * 2, $h - $i * 2, imagecolorallocatealpha($c, 0, 0, 0, 127 - ($i * 2)));
        }
    }

    // ── Asset loading ─────────────────────────────────────────────────────────

    private function loadAsset(string $relativePath): ?\GdImage
    {
        if (array_key_exists($relativePath, $this->assetCache)) {
            return $this->assetCache[$relativePath];
        }

        $fullPath = storage_path('app/public/' . ltrim($relativePath, '/'));
        if (! file_exists($fullPath)) {
            return $this->assetCache[$relativePath] = null;
        }

        $type = @exif_imagetype($fullPath);
        $img  = match ($type) {
            IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($fullPath),
            IMAGETYPE_GIF  => @imagecreatefromgif($fullPath),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
            default        => null,
        };

        if ($img) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        return $this->assetCache[$relativePath] = $img;
    }

    private function pasteAsset(\GdImage $c, \GdImage $asset, int $cx, int $cy, int $d): void
    {
        $scaled = imagecreatetruecolor($d, $d);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $asset, 0, 0, 0, 0, $d, $d, imagesx($asset), imagesy($asset));

        imagealphablending($c, true);
        imagecopy($c, $scaled, $cx - (int)($d / 2), $cy - (int)($d / 2), 0, 0, $d, $d);
        imagealphablending($c, false);
        imagedestroy($scaled);
    }

    private function freeAssetCache(): void
    {
        foreach ($this->assetCache as $img) {
            if ($img instanceof \GdImage) {
                imagedestroy($img);
            }
        }
        $this->assetCache = [];
    }

    // ── Storage helpers ───────────────────────────────────────────────────────

    private function storagePath(LoyaltyCard $card, string $scale): string
    {
        return $this->storageDir() . '/' . $this->filename($card, $scale);
    }

    private function filename(LoyaltyCard $card, string $scale): string
    {
        $program  = $card->loyaltyProgram;
        $business = $program->business;

        $hash = substr(md5(implode('|', [
            $business->primary_color,
            $business->secondary_color,
            $program->stamp_icon ?? '',
            $program->stamp_style ?? 'minimal',
            $program->filled_stamp_image ?? '',
            $program->empty_stamp_image ?? '',
            $program->reward_badge_image ?? '',
            $program->stamp_scale ?? '1.00',
            $program->stamp_spacing ?? '12',
        ])), 0, 8);

        return "apple_strip_{$program->id}_{$card->stamps_collected}of{$program->total_stamps}_{$hash}_{$scale}.png";
    }

    private function storageDir(): string
    {
        $dir = storage_path('app/apple-pass/strips');
        is_dir($dir) || mkdir($dir, 0755, true);

        return $dir;
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
