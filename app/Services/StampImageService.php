<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class StampImageService
{
    // Google Wallet hero image ratio is ~3.07:1 (1032×336)
    private const W = 1032;
    private const H = 300;

    // Internal render scale for anti-aliasing (2× then downscale)
    private const SCALE = 3;

    private const MAX_PER_ROW = 12;

    private string $fallbackFontPath;

    public function __construct()
    {
        $this->fallbackFontPath = resource_path('fonts/Roboto-Bold.ttf');
    }

    /**
     * Return the public URL for the card's stamp image, generating it if needed.
     */
    public function urlFor(LoyaltyCard $card): ?string
    {
        // Skip image generation if APP_URL is localhost (Google can't fetch it)
        $appUrl = config('app.url', '');
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            return null;
        }

        $path = $this->pathFor($card);

        if (! file_exists($path)) {
            $this->generate($card, $path);
        }

        return asset('storage/loyalty/stamps/' . basename($path));
    }

    /**
     * Regenerate the image even if it already exists (called after stamp update).
     */
    public function regenerateFor(LoyaltyCard $card): ?string
    {
        $appUrl = config('app.url', '');
        if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            return null;
        }

        $path = $this->pathFor($card);
        $this->generate($card, $path);

        return asset('storage/loyalty/stamps/' . basename($path));
    }

    /**
     * Delete cached images for all possible stamp counts of this card's program.
     */
    public function clearFor(LoyaltyCard $card): void
    {
        $program = $card->loyaltyProgram;
        $dir = $this->storageDir();

        for ($i = 0; $i <= $program->total_stamps; $i++) {
            $file = $dir . '/' . $this->filename($program, $i);
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    private function generate(LoyaltyCard $card, string $outputPath): void
    {
        $program  = $card->loyaltyProgram;
        $business = $program->business;
        $total    = $program->total_stamps;
        $filled   = min($card->stamps_collected, $total);

        // Which stamp positions have a milestone reward (1-indexed)
        $program->load('milestones');
        $milestoneCounts = array_flip($program->milestoneCounts());

        $fontPath = $program->fontPath();

        // Render at SCALE× for anti-aliasing, then downsample
        $rW = self::W * self::SCALE;
        $rH = self::H * self::SCALE;

        $img = imagecreatetruecolor($rW, $rH);
        imagesavealpha($img, true);
        imageantialias($img, true);

        // ── Background ──────────────────────────────────────────────────────
        [$br, $bg, $bb] = $this->hexToRgb($business->primary_color ?? '#1a1a2e');
        $bgColor = imagecolorallocate($img, $br, $bg, $bb);
        imagefill($img, 0, 0, $bgColor);

        $this->drawVignette($img, $rW, $rH, $br, $bg, $bb);

        // ── Stamp layout ────────────────────────────────────────────────────
        $rows   = (int) ceil($total / self::MAX_PER_ROW);
        $perRow = (int) ceil($total / $rows);

        $stampD = (int) min(
            floor(($rW * 0.85 - ($perRow - 1) * 0.18 * $rW / $perRow) / $perRow),
            floor($rH * 0.42)
        );
        $gap = (int) max(12 * self::SCALE, floor($rW * 0.015));

        $rowHeight  = $stampD + $gap;
        $totalRowsH = $rows * $rowHeight - $gap;
        $startY     = ($rH - $totalRowsH) / 2;

        [$fr, $fg, $fb] = $this->hexToRgb($business->secondary_color ?? '#ffffff');
        $stampFill    = imagecolorallocate($img, $fr, $fg, $fb);
        $stampEmpty   = imagecolorallocatealpha($img, $fr, $fg, $fb, 90);
        $stampOutline = imagecolorallocatealpha($img, $fr, $fg, $fb, 60);
        $iconColor    = imagecolorallocate($img, $br, $bg, $bb);

        // Gold badge color for milestone stamps
        $goldFill    = imagecolorallocate($img, 255, 200, 30);
        $goldOutline = imagecolorallocate($img, 200, 140, 10);

        $stampN = 0;
        for ($row = 0; $row < $rows; $row++) {
            $count  = ($row < $rows - 1) ? $perRow : ($total - $row * $perRow);
            $rowW   = $count * $stampD + ($count - 1) * $gap;
            $startX = ($rW - $rowW) / 2;
            $cy     = (int) ($startY + $row * $rowHeight + $stampD / 2);

            for ($col = 0; $col < $count; $col++, $stampN++) {
                $cx          = (int) ($startX + $col * ($stampD + $gap) + $stampD / 2);
                $stampNumber = $stampN + 1;
                $isMilestone = isset($milestoneCounts[$stampNumber]);

                if ($stampN < $filled) {
                    $this->drawFilledStamp($img, $cx, $cy, $stampD, $stampFill, $iconColor, $program->stamp_icon);
                } else {
                    $this->drawEmptyStamp($img, $cx, $cy, $stampD, $stampEmpty, $stampOutline);
                }

                // Gold prize badge in top-right corner of milestone stamps
                if ($isMilestone) {
                    $this->drawMilestoneBadge($img, $cx, $cy, $stampD, $goldFill, $goldOutline, $fontPath);
                }
            }
        }

        // ── Progress text strip ──────────────────────────────────────────────
        $nextMilestone = $program->milestones()->where('stamp_count', '>', $filled)->first();
        $this->drawProgressStrip($img, $rW, $rH, $filled, $total, $fr, $fg, $fb, $br, $bg, $bb, $nextMilestone, $fontPath);

        // ── Downsample to final size ─────────────────────────────────────────
        $final = imagecreatetruecolor(self::W, self::H);
        imagesavealpha($final, true);
        $transparent = imagecolorallocatealpha($final, 0, 0, 0, 127);
        imagefill($final, 0, 0, $transparent);
        imagecopyresampled($final, $img, 0, 0, 0, 0, self::W, self::H, $rW, $rH);

        imagedestroy($img);

        is_dir(dirname($outputPath)) || mkdir(dirname($outputPath), 0755, true);
        imagepng($final, $outputPath, 9);
        imagedestroy($final);
    }

    private function drawFilledStamp(\GdImage $img, int $cx, int $cy, int $d, int $fill, int $icon, string $stampIcon): void
    {
        // Outer glow (soft)
        $r = (int) ($d / 2);
        for ($i = 3; $i >= 0; $i--) {
            [$rr, $gg, $bb, $a] = $this->glowStep($i);
            $glow = imagecolorallocatealpha($img, $rr, $gg, $bb, $a);
            imagefilledellipse($img, $cx, $cy, $d + $i * 4, $d + $i * 4, $glow);
        }

        // Main circle
        imagefilledellipse($img, $cx, $cy, $d, $d, $fill);

        // Inner icon — draw a smaller filled circle in bg color (like a wax seal)
        $innerD = (int) ($d * 0.38);
        if ($innerD > 4) {
            imagefilledellipse($img, $cx, $cy, $innerD, $innerD, $icon);
        }
    }

    private function drawEmptyStamp(\GdImage $img, int $cx, int $cy, int $d, int $fill, int $outline): void
    {
        // Filled circle with transparency
        imagefilledellipse($img, $cx, $cy, $d, $d, $fill);

        // Outline ring (2px scaled)
        $thick = max(2, (int) ($d * 0.05));
        for ($t = 0; $t < $thick; $t++) {
            imageellipse($img, $cx, $cy, $d - $t * 2, $d - $t * 2, $outline);
        }
    }

    private function drawVignette(\GdImage $img, int $w, int $h, int $r, int $g, int $b): void
    {
        // Darken 4 gradient bands from edges
        $bands = 8;
        for ($i = 0; $i < $bands; $i++) {
            $alpha = (int) (($bands - $i) * 1.5);
            $dark  = imagecolorallocatealpha($img, 0, 0, 0, 127 - $alpha);
            imagerectangle($img, $i * 2, $i * 2, $w - $i * 2, $h - $i * 2, $dark);
        }
    }

    private function drawMilestoneBadge(\GdImage $img, int $cx, int $cy, int $stampD, int $gold, int $goldOutline, string $fontPath): void
    {
        $r    = (int) ($stampD / 2);
        $badgeR = (int) max(7 * self::SCALE, $r * 0.28);
        $bx   = $cx + (int) ($r * 0.65);
        $by   = $cy - (int) ($r * 0.65);

        // Outer dark ring
        imagefilledellipse($img, $bx, $by, $badgeR * 2 + 4, $badgeR * 2 + 4, $goldOutline);
        // Gold fill
        imagefilledellipse($img, $bx, $by, $badgeR * 2, $badgeR * 2, $gold);

        // Small star glyph using TTF or fallback dot
        if (file_exists($fontPath)) {
            $starSize  = (int) ($badgeR * 0.9);
            $darkColor = imagecolorallocate($img, 60, 30, 0);
            $bbox      = imagettfbbox($starSize, 0, $fontPath, '*');
            $tw        = abs($bbox[4] - $bbox[0]);
            $th        = abs($bbox[5] - $bbox[1]);
            imagettftext($img, $starSize, 0, (int) ($bx - $tw / 2), (int) ($by + $th / 2), $darkColor, $fontPath, '*');
        }
    }

    private function drawProgressStrip(
        \GdImage $img,
        int $w,
        int $h,
        int $filled,
        int $total,
        int $fr,
        int $fg,
        int $fb,
        int $br,
        int $bg,
        int $bb,
        mixed $nextMilestone,
        string $fontPath,
    ): void {
        $stripH = (int) ($h * 0.20);
        $strip  = imagecolorallocatealpha($img, 0, 0, 0, 80);
        imagefilledrectangle($img, 0, $h - $stripH, $w, $h, $strip);

        // Progress bar fill
        $progress = $total > 0 ? $filled / $total : 0;
        $barH     = (int) ($stripH * 0.20);
        $barY     = $h - $barH;
        $barW     = (int) ($w * $progress);
        $barColor = imagecolorallocatealpha($img, $fr, $fg, $fb, 30);
        imagefilledrectangle($img, 0, $barY, $barW, $h, $barColor);

        // Build progress text
        if ($nextMilestone) {
            $remaining = $nextMilestone->stamp_count - $filled;
            $text = $remaining === 1
                ? "1 visita para: {$nextMilestone->reward_title}!"
                : "Premio en {$remaining} visitas: {$nextMilestone->reward_title}";
        } elseif ($filled >= $total) {
            $text = '¡Premio disponible!';
        } else {
            $remaining = $total - $filled;
            $text = $remaining === 1
                ? "{$filled}/{$total} — ¡1 visita mas!"
                : "{$filled}/{$total} — faltan {$remaining} visitas";
        }

        if (file_exists($fontPath)) {
            $fontSize  = (int) ($stripH * 0.36);
            $textColor = imagecolorallocate($img, $fr, $fg, $fb);
            $bbox      = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textW     = abs($bbox[4] - $bbox[0]);
            $textX     = ($w - $textW) / 2;
            $textY     = $h - (int) (($stripH - $fontSize) / 2) - 4;
            imagettftext($img, $fontSize, 0, (int) $textX, (int) $textY, $textColor, $fontPath, $text);
        } else {
            $textColor = imagecolorallocate($img, $fr, $fg, $fb);
            imagestring($img, 3, (int) ($w / 2 - 60), (int) ($h - $stripH + 6), $text, $textColor);
        }
    }

    /** @return int[] [r,g,b,alpha] */
    private function glowStep(int $i): array
    {
        return match ($i) {
            3 => [255, 255, 255, 120],
            2 => [255, 255, 255, 105],
            1 => [255, 255, 255, 90],
            default => [255, 255, 255, 75],
        };
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function pathFor(LoyaltyCard $card): string
    {
        return $this->storageDir() . '/' . $this->filename($card->loyaltyProgram, $card->stamps_collected);
    }

    private function filename(LoyaltyProgram $program, int $stamps): string
    {
        $business = $program->business;
        $hash = substr(md5($business->primary_color . $business->secondary_color . $program->stamp_icon . ($program->card_font ?? 'roboto')), 0, 8);

        return "stamp_{$program->id}_{$stamps}of{$program->total_stamps}_{$hash}.png";
    }

    private function storageDir(): string
    {
        $dir = storage_path('app/public/loyalty/stamps');
        is_dir($dir) || mkdir($dir, 0755, true);

        return $dir;
    }
}
