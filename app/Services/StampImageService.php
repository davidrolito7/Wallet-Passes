<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;

/**
 * Renders a hero image (1032×300 px) for Google Wallet loyalty cards.
 *
 * Stamp assets are loaded from storage/app/public/ and blended onto a
 * theme-styled canvas.  When no custom asset is configured the renderer
 * falls back to a procedural style that matches the chosen theme.
 *
 * Themes:   minimal · luxury · neon · coffee · retro
 * Assets:   filled_stamp_image, empty_stamp_image, reward_badge_image
 */
class StampImageService
{
    // Final output size — matches Google Wallet hero ratio (≈3.44:1)
    private const W = 1032;
    private const H = 300;

    // Super-sampling factor for anti-aliasing (render at 3× then downsample)
    private const SCALE = 3;

    private const MAX_PER_ROW = 12;

    /** @var array<string, \GdImage|null> */
    private array $assetCache = [];

    // ── Public API ────────────────────────────────────────────────────────────

    public function urlFor(LoyaltyCard $card): ?string
    {
        if ($this->isLocalEnvironment()) {
            return null;
        }

        $path = $this->pathFor($card);

        if (! file_exists($path)) {
            $this->generate($card, $path);
        }

        return asset('storage/loyalty/stamps/' . basename($path));
    }

    public function regenerateFor(LoyaltyCard $card): ?string
    {
        if ($this->isLocalEnvironment()) {
            return null;
        }

        $path = $this->pathFor($card);
        $this->generate($card, $path);

        return asset('storage/loyalty/stamps/' . basename($path));
    }

    /** Clears all cached images for this program (called after config change). */
    public function clearFor(LoyaltyCard $card): void
    {
        $dir     = $this->storageDir();
        $pattern = "{$dir}/stamp_{$card->loyalty_program_id}_*.png";

        foreach (glob($pattern) ?: [] as $file) {
            @unlink($file);
        }
    }

    // ── Core renderer ─────────────────────────────────────────────────────────

    private function generate(LoyaltyCard $card, string $outputPath): void
    {
        $program  = $card->loyaltyProgram;
        $business = $program->business;
        $total    = $program->total_stamps;
        $filled   = min($card->stamps_collected, $total);

        $program->load('milestones');
        $milestoneCounts = array_flip($program->milestoneCounts());

        $style   = $program->stamp_style ?? 'minimal';
        $scale   = max(0.5, min(1.5, (float) ($program->stamp_scale ?? 1.0)));
        $spacing = max(5, min(40, (int) ($program->stamp_spacing ?? 15)));
        $font    = $program->fontPath();

        [$bgR, $bgG, $bgB] = $this->hexToRgb($business->primary_color ?? '#1a1a2e');
        [$fgR, $fgG, $fgB] = $this->hexToRgb($business->secondary_color ?? '#ffffff');

        $rW = self::W * self::SCALE;
        $rH = self::H * self::SCALE;

        $canvas = imagecreatetruecolor($rW, $rH);
        imagesavealpha($canvas, true);
        imageantialias($canvas, true);

        // ── Background ───────────────────────────────────────────────────────
        $this->drawBackground($canvas, $rW, $rH, $style, $bgR, $bgG, $bgB);

        // ── Stamp layout ─────────────────────────────────────────────────────
        $rows   = (int) ceil($total / self::MAX_PER_ROW);
        $perRow = (int) ceil($total / $rows);

        $gapBase = (int) max(10 * self::SCALE, (int) ($rW * $spacing / 1000));
        $stampD  = (int) min(
            floor(($rW * 0.88 - ($perRow - 1) * $gapBase) / $perRow),
            floor($rH * 0.44)
        );
        $stampD  = (int) ($stampD * $scale);
        $gap     = (int) max(8 * self::SCALE, $gapBase);

        $rowHeight  = $stampD + $gap;
        $totalRowsH = $rows * $rowHeight - $gap;
        $startY     = (int) (($rH - $totalRowsH) / 2);

        $ctx = [
            'style'       => $style,
            'fgR'         => $fgR, 'fgG' => $fgG, 'fgB' => $fgB,
            'bgR'         => $bgR, 'bgG' => $bgG, 'bgB' => $bgB,
            'font'        => $font,
            'filledAsset' => $program->filled_stamp_image,
            'emptyAsset'  => $program->empty_stamp_image,
            'badgeAsset'  => $program->reward_badge_image,
        ];

        $stampN = 0;
        for ($row = 0; $row < $rows; $row++) {
            $count  = ($row < $rows - 1) ? $perRow : ($total - $row * $perRow);
            $rowW   = $count * $stampD + ($count - 1) * $gap;
            $startX = (int) (($rW - $rowW) / 2);
            $cy     = (int) ($startY + $row * $rowHeight + $stampD / 2);

            for ($col = 0; $col < $count; $col++, $stampN++) {
                $cx          = (int) ($startX + $col * ($stampD + $gap) + $stampD / 2);
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

        // ── Progress strip ───────────────────────────────────────────────────
        $nextMilestone = $program->milestones()->where('stamp_count', '>', $filled)->first();
        $this->drawProgressStrip($canvas, $rW, $rH, $filled, $total, $ctx, $nextMilestone);

        // ── Downsample to output size ─────────────────────────────────────────
        $output = imagecreatetruecolor(self::W, self::H);
        imagesavealpha($output, true);
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $transparent);
        imagecopyresampled($output, $canvas, 0, 0, 0, 0, self::W, self::H, $rW, $rH);

        imagedestroy($canvas);
        $this->freeAssetCache();

        is_dir(dirname($outputPath)) || mkdir(dirname($outputPath), 0755, true);
        imagepng($output, $outputPath, 9);
        imagedestroy($output);
    }

    // ── Asset loading & pasting ───────────────────────────────────────────────

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

        $img = match ($type) {
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

    /**
     * Pastes a pre-loaded asset onto the canvas, centered at (cx, cy), scaled to d×d.
     * Preserves full alpha channel.
     */
    private function pasteAsset(\GdImage $canvas, \GdImage $asset, int $cx, int $cy, int $d): void
    {
        $srcW = imagesx($asset);
        $srcH = imagesy($asset);

        $scaled = imagecreatetruecolor($d, $d);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefill($scaled, 0, 0, $transparent);
        imagecopyresampled($scaled, $asset, 0, 0, 0, 0, $d, $d, $srcW, $srcH);

        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, $cx - (int) ($d / 2), $cy - (int) ($d / 2), 0, 0, $d, $d);
        imagealphablending($canvas, false);

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

    // ── Stamp dispatchers ─────────────────────────────────────────────────────

    private function renderFilledStamp(\GdImage $canvas, int $cx, int $cy, int $d, array $ctx): void
    {
        if ($ctx['filledAsset'] && ($asset = $this->loadAsset($ctx['filledAsset']))) {
            $this->pasteAsset($canvas, $asset, $cx, $cy, $d);

            return;
        }

        match ($ctx['style']) {
            'luxury' => $this->drawLuxuryFilled($canvas, $cx, $cy, $d),
            'neon'   => $this->drawNeonFilled($canvas, $cx, $cy, $d, $ctx),
            'coffee' => $this->drawCoffeeFilled($canvas, $cx, $cy, $d),
            'retro'  => $this->drawRetroFilled($canvas, $cx, $cy, $d),
            default  => $this->drawMinimalFilled($canvas, $cx, $cy, $d, $ctx),
        };
    }

    private function renderEmptyStamp(\GdImage $canvas, int $cx, int $cy, int $d, array $ctx): void
    {
        if ($ctx['emptyAsset'] && ($asset = $this->loadAsset($ctx['emptyAsset']))) {
            $this->pasteAsset($canvas, $asset, $cx, $cy, $d);

            return;
        }

        match ($ctx['style']) {
            'luxury' => $this->drawLuxuryEmpty($canvas, $cx, $cy, $d),
            'neon'   => $this->drawNeonEmpty($canvas, $cx, $cy, $d, $ctx),
            'coffee' => $this->drawCoffeeEmpty($canvas, $cx, $cy, $d),
            'retro'  => $this->drawRetroEmpty($canvas, $cx, $cy, $d),
            default  => $this->drawMinimalEmpty($canvas, $cx, $cy, $d, $ctx),
        };
    }

    private function renderMilestoneBadge(
        \GdImage $canvas,
        int $cx,
        int $cy,
        int $d,
        array $ctx,
        bool $isFinal,
    ): void {
        $badgeD = (int) ($d * 0.38);
        $r      = (int) ($d / 2);
        $bx     = $cx + (int) ($r * 0.64);
        $by     = $cy - (int) ($r * 0.64);

        if ($ctx['badgeAsset'] && ($asset = $this->loadAsset($ctx['badgeAsset']))) {
            $this->pasteAsset($canvas, $asset, $bx, $by, $badgeD);

            return;
        }

        // Procedural badge
        $br = (int) ($badgeD / 2);

        // Drop shadow
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 85);
        imagefilledellipse($canvas, $bx + 2, $by + 2, $badgeD + 4, $badgeD + 4, $shadow);

        // Badge face: gold for final reward, green for intermediate
        [$fillR, $fillG, $fillB]       = $isFinal ? [255, 210, 40] : [60, 200, 110];
        [$outlineR, $outlineG, $outlineB] = $isFinal ? [170, 125, 5] : [25, 130, 65];

        $badgeOutline = imagecolorallocate($canvas, $outlineR, $outlineG, $outlineB);
        $badgeFill    = imagecolorallocate($canvas, $fillR, $fillG, $fillB);
        $highlight    = imagecolorallocatealpha($canvas, 255, 255, 255, 60);

        imagefilledellipse($canvas, $bx, $by, $badgeD + 3, $badgeD + 3, $badgeOutline);
        imagefilledellipse($canvas, $bx, $by, $badgeD, $badgeD, $badgeFill);

        // Subtle highlight arc (top-left)
        imagefilledellipse($canvas, $bx - (int) ($br * 0.2), $by - (int) ($br * 0.2), (int) ($badgeD * 0.55), (int) ($badgeD * 0.55), $highlight);

        // Star / asterisk glyph
        $font = $ctx['font'];
        if (file_exists($font)) {
            $sz   = (int) ($br * 0.85);
            $dark = imagecolorallocate($canvas, 50, 25, 0);
            $bbox = imagettfbbox($sz, 0, $font, '*');
            $tw   = abs($bbox[4] - $bbox[0]);
            $th   = abs($bbox[5] - $bbox[1]);
            imagettftext($canvas, $sz, 0, (int) ($bx - $tw / 2), (int) ($by + $th / 2), $dark, $font, '*');
        }
    }

    // ── Theme: Minimal ────────────────────────────────────────────────────────

    private function drawMinimalFilled(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];

        // Soft halo
        for ($i = 4; $i >= 1; $i--) {
            $glow = imagecolorallocatealpha($c, $r, $g, $b, 118 - $i * 3);
            imagefilledellipse($c, $cx, $cy, $d + $i * 5, $d + $i * 5, $glow);
        }

        $fill  = imagecolorallocate($c, $r, $g, $b);
        imagefilledellipse($c, $cx, $cy, $d, $d, $fill);

        // Inner dot in background color
        $inner = imagecolorallocate($c, $ctx['bgR'], $ctx['bgG'], $ctx['bgB']);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.32), (int) ($d * 0.32), $inner);
    }

    private function drawMinimalEmpty(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];

        $emptyFill    = imagecolorallocatealpha($c, $r, $g, $b, 105);
        $emptyOutline = imagecolorallocatealpha($c, $r, $g, $b, 70);

        imagefilledellipse($c, $cx, $cy, $d, $d, $emptyFill);

        $thick = max(2, (int) ($d * 0.055));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, $emptyOutline);
        }
    }

    // ── Theme: Luxury (dark + gold) ───────────────────────────────────────────

    private function drawLuxuryFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        // Outer gold glow
        for ($i = 6; $i >= 0; $i--) {
            $a    = 112 - $i * 3;
            $glow = imagecolorallocatealpha($c, 212, 175, 55, $a);
            imagefilledellipse($c, $cx, $cy, $d + $i * 4, $d + $i * 4, $glow);
        }

        $dark  = imagecolorallocate($c, 130, 100, 20);
        $mid   = imagecolorallocate($c, 212, 175, 55);
        $light = imagecolorallocate($c, 245, 215, 100);

        imagefilledellipse($c, $cx, $cy, $d, $d, $dark);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.90), (int) ($d * 0.90), $mid);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.72), (int) ($d * 0.72), $light);

        // Highlight arc
        $hl = imagecolorallocatealpha($c, 255, 255, 255, 65);
        imagefilledellipse($c, $cx - (int) ($d * 0.12), $cy - (int) ($d * 0.12), (int) ($d * 0.42), (int) ($d * 0.42), $hl);

        // Center jewel
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.24), (int) ($d * 0.24), $dark);
    }

    private function drawLuxuryEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $dimFill = imagecolorallocatealpha($c, 212, 175, 55, 108);
        $ring    = imagecolorallocatealpha($c, 212, 175, 55, 65);

        imagefilledellipse($c, $cx, $cy, $d, $d, $dimFill);

        $thick = max(3, (int) ($d * 0.07));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, $ring);
        }
    }

    // ── Theme: Neon (dark bg + electric glow) ────────────────────────────────

    private function drawNeonFilled(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];

        // Intense bloom
        for ($i = 10; $i >= 0; $i--) {
            $a    = 105 + $i * 2;
            $glow = imagecolorallocatealpha($c, $r, $g, $b, $a);
            imagefilledellipse($c, $cx, $cy, $d + $i * 5, $d + $i * 5, $glow);
        }

        // Bright core
        $br    = min(255, $r + 90);
        $bg    = min(255, $g + 90);
        $bb    = min(255, $b + 90);
        $core  = imagecolorallocate($c, $br, $bg, $bb);
        imagefilledellipse($c, $cx, $cy, $d, $d, $core);

        // White flash
        $flash = imagecolorallocatealpha($c, 255, 255, 255, 22);
        imagefilledellipse($c, $cx - (int) ($d * 0.14), $cy - (int) ($d * 0.14), (int) ($d * 0.48), (int) ($d * 0.48), $flash);
    }

    private function drawNeonEmpty(\GdImage $c, int $cx, int $cy, int $d, array $ctx): void
    {
        [$r, $g, $b] = [$ctx['fgR'], $ctx['fgG'], $ctx['fgB']];

        $dimFill = imagecolorallocatealpha($c, $r, $g, $b, 108);
        $ring    = imagecolorallocatealpha($c, $r, $g, $b, 68);

        imagefilledellipse($c, $cx, $cy, $d, $d, $dimFill);

        $thick = max(2, (int) ($d * 0.05));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, $ring);
        }
    }

    // ── Theme: Coffee (warm browns) ───────────────────────────────────────────

    private function drawCoffeeFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $shadow = imagecolorallocatealpha($c, 0, 0, 0, 88);
        imagefilledellipse($c, $cx + 3, $cy + 3, $d, $d, $shadow);

        $espresso = imagecolorallocate($c, 55, 25, 8);
        $roast    = imagecolorallocate($c, 110, 55, 18);
        $latte    = imagecolorallocate($c, 195, 135, 75);
        $cream    = imagecolorallocate($c, 245, 220, 180);

        imagefilledellipse($c, $cx, $cy, $d, $d, $espresso);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.88), (int) ($d * 0.88), $roast);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.70), (int) ($d * 0.70), $latte);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.38), (int) ($d * 0.38), $cream);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.14), (int) ($d * 0.14), $espresso);
    }

    private function drawCoffeeEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $warmFill = imagecolorallocatealpha($c, 170, 110, 50, 105);
        $warmRing = imagecolorallocatealpha($c, 170, 110, 50, 72);

        imagefilledellipse($c, $cx, $cy, $d, $d, $warmFill);

        $thick = max(2, (int) ($d * 0.06));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, $warmRing);
        }
    }

    // ── Theme: Retro (ink stamp look) ─────────────────────────────────────────

    private function drawRetroFilled(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $ink  = imagecolorallocate($c, 55, 38, 18);
        $aged = imagecolorallocate($c, 175, 148, 95);
        $mid  = imagecolorallocate($c, 110, 82, 42);

        // Outer ink ring (thick)
        imagefilledellipse($c, $cx, $cy, $d, $d, $ink);

        $inner = (int) ($d * 0.82);
        imagefilledellipse($c, $cx, $cy, $inner, $inner, $aged);

        // Texture lines (simulate worn ink)
        $texture = imagecolorallocatealpha($c, 55, 38, 18, 85);
        for ($t = 0; $t < 3; $t++) {
            imageellipse($c, $cx, $cy, $inner - $t * 4, $inner - $t * 4, $texture);
        }

        // Inner stamp circle
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.48), (int) ($d * 0.48), $ink);
        imagefilledellipse($c, $cx, $cy, (int) ($d * 0.32), (int) ($d * 0.32), $mid);
    }

    private function drawRetroEmpty(\GdImage $c, int $cx, int $cy, int $d): void
    {
        $agedFill = imagecolorallocatealpha($c, 180, 155, 100, 108);
        $agedRing = imagecolorallocatealpha($c, 100, 78, 40, 72);

        imagefilledellipse($c, $cx, $cy, $d, $d, $agedFill);

        $thick = max(2, (int) ($d * 0.06));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($c, $cx, $cy, $d - $t * 2, $d - $t * 2, $agedRing);
        }
    }

    // ── Background ────────────────────────────────────────────────────────────

    private function drawBackground(
        \GdImage $canvas,
        int $w,
        int $h,
        string $style,
        int $bgR,
        int $bgG,
        int $bgB,
    ): void {
        switch ($style) {
            case 'luxury':
                $this->drawGradient($canvas, $w, $h, [8, 6, 4], [28, 20, 8]);
                break;

            case 'neon':
                $this->drawGradient($canvas, $w, $h, [4, 4, 14], [12, 5, 22]);
                break;

            case 'coffee':
                $this->drawGradient($canvas, $w, $h, [38, 20, 8], [60, 32, 12]);
                break;

            case 'retro':
                $this->drawGradient($canvas, $w, $h, [52, 42, 25], [78, 62, 38]);
                break;

            default: // minimal — use business color
                $bgColor = imagecolorallocate($canvas, $bgR, $bgG, $bgB);
                imagefill($canvas, 0, 0, $bgColor);
                $this->drawVignette($canvas, $w, $h);
                break;
        }
    }

    private function drawGradient(\GdImage $canvas, int $w, int $h, array $from, array $to): void
    {
        for ($y = 0; $y < $h; $y++) {
            $t     = $y / $h;
            $r     = (int) ($from[0] + ($to[0] - $from[0]) * $t);
            $g     = (int) ($from[1] + ($to[1] - $from[1]) * $t);
            $b     = (int) ($from[2] + ($to[2] - $from[2]) * $t);
            $color = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle($canvas, 0, $y, $w, $y + 1, $color);
        }
        $this->drawVignette($canvas, $w, $h);
    }

    private function drawVignette(\GdImage $canvas, int $w, int $h): void
    {
        $bands = 10;
        for ($i = 0; $i < $bands; $i++) {
            $alpha = (int) (($bands - $i) * 2);
            $dark  = imagecolorallocatealpha($canvas, 0, 0, 0, 127 - $alpha);
            imagerectangle($canvas, $i * 2, $i * 2, $w - $i * 2, $h - $i * 2, $dark);
        }
    }

    // ── Progress strip ────────────────────────────────────────────────────────

    private function drawProgressStrip(
        \GdImage $canvas,
        int $w,
        int $h,
        int $filled,
        int $total,
        array $ctx,
        mixed $nextMilestone,
    ): void {
        $stripH = (int) ($h * 0.20);
        $stripY = $h - $stripH;

        $stripAlpha = match ($ctx['style']) {
            'luxury', 'neon' => 88,
            'retro'          => 72,
            default          => 80,
        };

        $strip = imagecolorallocatealpha($canvas, 0, 0, 0, $stripAlpha);
        imagefilledrectangle($canvas, 0, $stripY, $w, $h, $strip);

        // Progress bar accent line at the very bottom
        $progress = $total > 0 ? $filled / $total : 0;
        $barH     = (int) ($stripH * 0.16);
        $barW     = (int) ($w * $progress);

        $barColor = match ($ctx['style']) {
            'luxury' => imagecolorallocatealpha($canvas, 212, 175, 55, 30),
            'neon'   => imagecolorallocatealpha($canvas, $ctx['fgR'], $ctx['fgG'], $ctx['fgB'], 18),
            'coffee' => imagecolorallocatealpha($canvas, 180, 120, 60, 32),
            'retro'  => imagecolorallocatealpha($canvas, 140, 110, 70, 32),
            default  => imagecolorallocatealpha($canvas, $ctx['fgR'], $ctx['fgG'], $ctx['fgB'], 30),
        };
        imagefilledrectangle($canvas, 0, $h - $barH, $barW, $h, $barColor);

        // Text
        if ($nextMilestone) {
            $remaining = $nextMilestone->stamp_count - $filled;
            $text = $remaining === 1
                ? '¡1 visita para: ' . $nextMilestone->reward_title . '!'
                : "Premio en {$remaining} visitas: " . $nextMilestone->reward_title;
        } elseif ($filled >= $total) {
            $text = '¡Premio disponible!';
        } else {
            $remaining = $total - $filled;
            $text = $remaining === 1
                ? "{$filled}/{$total} — ¡última visita!"
                : "{$filled}/{$total} — faltan {$remaining} visitas";
        }

        $textColor = match ($ctx['style']) {
            'luxury' => imagecolorallocate($canvas, 212, 175, 55),
            'coffee' => imagecolorallocate($canvas, 245, 220, 180),
            'retro'  => imagecolorallocate($canvas, 200, 170, 120),
            default  => imagecolorallocate($canvas, $ctx['fgR'], $ctx['fgG'], $ctx['fgB']),
        };

        $font = $ctx['font'];
        if (file_exists($font)) {
            $fontSize = (int) ($stripH * 0.36);
            $bbox     = imagettfbbox($fontSize, 0, $font, $text);
            $textW    = abs($bbox[4] - $bbox[0]);
            $textX    = (int) (($w - $textW) / 2);
            $textY    = $h - (int) (($stripH - $fontSize) / 2) - 4;
            imagettftext($canvas, $fontSize, 0, $textX, $textY, $textColor, $font, $text);
        } else {
            imagestring($canvas, 3, (int) ($w / 2 - 80), $stripY + 8, $text, $textColor);
        }
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private function pathFor(LoyaltyCard $card): string
    {
        return $this->storageDir() . '/' . $this->filename($card->loyaltyProgram, $card->stamps_collected);
    }

    private function filename(LoyaltyProgram $program, int $stamps): string
    {
        $business = $program->business;

        $hash = substr(md5(implode('|', [
            $business->primary_color,
            $business->secondary_color,
            $program->stamp_icon,
            $program->card_font ?? 'roboto',
            $program->stamp_style ?? 'minimal',
            $program->filled_stamp_image ?? '',
            $program->empty_stamp_image ?? '',
            $program->reward_badge_image ?? '',
            $program->stamp_scale ?? '1.00',
            $program->stamp_spacing ?? '15',
        ])), 0, 10);

        return "stamp_{$program->id}_{$stamps}of{$program->total_stamps}_{$hash}.png";
    }

    private function storageDir(): string
    {
        $dir = storage_path('app/public/loyalty/stamps');
        is_dir($dir) || mkdir($dir, 0755, true);

        return $dir;
    }

    private function isLocalEnvironment(): bool
    {
        $url = config('app.url', '');

        return str_contains($url, 'localhost') || str_contains($url, '127.0.0.1');
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
