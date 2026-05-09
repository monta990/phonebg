<?php
declare(strict_types=1);
if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

class PluginPhonebgBackground {
    public static string $lastError = '';

    /**
     * Validate plugin requirements before generating an image.
     */
    public static function checkRequirements(): array
    {
        $errors = [];
        if (!extension_loaded('gd')) {
            $errors[] = __('PHP GD extension is required', 'phonebg');
        }
        if (!is_readable(PluginPhonebgPaths::basePath())) {
            $errors[] = __('Template not found', 'phonebg');
        }
        try {
            $fontPath = PluginPhonebgPaths::getFontPath();
            if (!is_readable($fontPath)) {
                $errors[] = sprintf(
                    __('TTF font not found: %s', 'phonebg'),
                    $fontPath
                );
            }
        } catch (RuntimeException $e) {
            $errors[] = __('Plugin directory not found, unable to verify TTF font', 'phonebg');
        }
        return $errors;
    }

    /**
     * Shrink $size until $text fits within $maxWidth pixels.
     * Uses a proportional estimate first, then at most a few fine-tune steps.
     */
    private static function fitFontSize(string $font, string $text, int $size, int $maxWidth): int
    {
        $bbox = imagettfbbox($size, 0, $font, $text);
        if ($bbox === false || ($bbox[2] - $bbox[0]) <= $maxWidth) {
            return $size;
        }
        $tw   = $bbox[2] - $bbox[0];
        $size = max(10, (int)floor($size * $maxWidth / $tw));
        $bbox = imagettfbbox($size, 0, $font, $text);
        while ($bbox !== false && $size > 10 && ($bbox[2] - $bbox[0]) > $maxWidth) {
            $size--;
            $bbox = imagettfbbox($size, 0, $font, $text);
        }
        return $size;
    }

    /**
     * Draw text on image.
     * If $x <= 0 the text is horizontally centered.
     */
    private static function drawText(
        GdImage $img,
        int $size,
        int $x,
        int $y,
        int $color,
        string $font,
        string $text
    ): void {
        if ($x <= 0) {
            $bbox = imagettfbbox($size, 0, $font, $text);
            if ($bbox !== false) {
                $x = (int)((imagesx($img) - ($bbox[2] - $bbox[0])) / 2);
            } else {
                $x = 0;
            }
        }
        imagettftext($img, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * Generate the wallpaper PNG for the given phone and return its temp path.
     */
    public static function generatePNG(Phone $phone): string
    {
        $name      = $phone->getName();
        $mobileRaw = self::getPhoneLine($phone);

        if ($mobileRaw === null) {
            self::$lastError = __('The phone has no assigned line', 'phonebg');
            Session::addMessageAfterRedirect(self::$lastError, false, WARNING);
            return '';
        }

        $mobile = trim($mobileRaw);
        if ($mobile === '') {
            self::$lastError = __('The phone line number is empty', 'phonebg');
            Session::addMessageAfterRedirect(self::$lastError, false, WARNING);
            return '';
        }

        return self::renderPNG($name, $mobile, (string)$phone->getID());
    }

    /**
     * Generate a test wallpaper PNG with placeholder data and return its temp path.
     */
    public static function generateTestPNG(string $deviceName, string $phoneLine): string
    {
        return self::renderPNG($deviceName, $phoneLine, 'test');
    }

    /**
     * Render the wallpaper PNG onto the configured template and return the temp file path.
     */
    private static function renderPNG(string $deviceName, string $phoneLine, string $suffix): string
    {
        $templatePath = PluginPhonebgPaths::basePath();

        /* Anti-DoS: verify dimensions before loading into RAM */
        $imgInfo = @getimagesize($templatePath);
        if ($imgInfo === false) {
            Session::addMessageAfterRedirect(__('Could not read base image dimensions.', 'phonebg'), false, ERROR);
            return '';
        }
        $max_dimension = 8000;
        if ($imgInfo[0] > $max_dimension || $imgInfo[1] > $max_dimension) {
            Session::addMessageAfterRedirect(
                sprintf(
                    __('Image dimensions (%1$d x %2$d) exceed the maximum allowed (%3$d x %3$d) for security reasons.', 'phonebg'),
                    $imgInfo[0], $imgInfo[1], $max_dimension
                ),
                false,
                ERROR
            );
            return '';
        }
        if ($imgInfo[0] * $imgInfo[1] > 20_000_000) {
            Session::addMessageAfterRedirect(
                sprintf(__('Image too large (%1$d x %2$d px). Maximum 20 megapixels allowed.', 'phonebg'), $imgInfo[0], $imgInfo[1]),
                false,
                ERROR
            );
            return '';
        }

        $img = imagecreatefrompng($templatePath);
        if (!$img instanceof GdImage) {
            Session::addMessageAfterRedirect(__('Could not load base image', 'phonebg'), false, ERROR);
            return '';
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagealphablending($img, true);

        $cfg = PluginPhonebgConfig::getAll();
        try {
            $font = PluginPhonebgPaths::getFontPath((string)($cfg['font_file'] ?? 'DejaVuSans.ttf'));
        } catch (RuntimeException $e) {
            unset($img);
            Session::addMessageAfterRedirect(__('Plugin directory not found', 'phonebg'), false, ERROR);
            return '';
        }

        $hex   = ltrim((string)($cfg['font_color'] ?? '#000000'), '#');
        $color = imagecolorallocate(
            $img,
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2))
        );

        $maxWidth = imagesx($img) - 40;

        $nameSize = self::fitFontSize($font, $deviceName, max(10, (int)$cfg['name_size']), $maxWidth);
        self::drawText($img, $nameSize, (int)$cfg['name_x'], (int)$cfg['name_y'], $color, $font, $deviceName);

        $mobileSize = self::fitFontSize($font, $phoneLine, max(10, (int)$cfg['mobile_size']), $maxWidth);
        self::drawText($img, $mobileSize, (int)$cfg['mobile_x'], (int)$cfg['mobile_y'], $color, $font, $phoneLine);

        foreach ([1, 2] as $n) {
            if (($cfg["label{$n}_enabled"] ?? '0') !== '1') {
                continue;
            }
            $labelText = trim((string)($cfg["label{$n}_text"] ?? ''));
            if ($labelText === '') {
                continue;
            }
            $labelSize = self::fitFontSize($font, $labelText, max(10, (int)($cfg["label{$n}_size"] ?? 40)), $maxWidth);
            self::drawText($img, $labelSize, (int)($cfg["label{$n}_x"] ?? 0), (int)($cfg["label{$n}_y"] ?? 0), $color, $font, $labelText);
        }

        $out = GLPI_TMP_DIR . '/background_' . $suffix . '_' . uniqid() . '.png';
        imagepng($img, $out, 6);
        unset($img);

        return $out;
    }

    /**
     * Build HTML email body from plain-text with markdown (** * __) and nl2br.
     * Supports {name} and {line} token substitution.
     */
    public static function buildEmailHtml(
        string $body,
        string $footer,
        string $name,
        string $line,
        bool   $isTest = false
    ): string {
        $render = static function (string $text) use ($name, $line): string {
            $text = str_replace(['{name}', '{line}'], [$name, $line], $text);
            $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = preg_replace_callback('/\*\*(.+?)\*\*/s',
                static fn(array $m): string => '<span style="font-weight:bold">' . $m[1] . '</span>', $html);
            $html = preg_replace_callback('/\*(.+?)\*/s',
                static fn(array $m): string => '<span style="font-style:italic">' . $m[1] . '</span>', $html);
            $html = preg_replace_callback('/__(.+?)__/s',
                static fn(array $m): string => '<span style="text-decoration:underline">' . $m[1] . '</span>', $html);
            return nl2br($html);
        };

        $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#333;">';
        if ($isTest) {
            $html .= '<p style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'
                   . 'padding:8px 12px;font-size:12px;color:#856404;margin-bottom:16px;">&#9888; '
                   . htmlspecialchars(__('This is a test email sent from the plugin configuration.', 'phonebg'), ENT_QUOTES, 'UTF-8')
                   . '</p>';
        }
        $html .= '<p>' . $render($body) . '</p>';
        if (trim($footer) !== '') {
            $html .= '<hr style="border:none;border-top:1px solid #ddd;margin:12px 0;">';
            $html .= '<p style="font-size:11px;color:#999;">' . $render($footer) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get the phone line number from GLPI database
     */
    public static function getPhoneLine(Phone $phone): ?string
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['glpi_lines.caller_num'],
            'FROM'   => 'glpi_items_lines',
            'INNER JOIN' => [
                'glpi_lines' => [
                    'ON' => [
                        'glpi_items_lines' => 'lines_id',
                        'glpi_lines'       => 'id',
                    ]
                ]
            ],
            'WHERE'  => [
                'glpi_items_lines.itemtype'  => 'Phone',
                'glpi_items_lines.items_id'  => (int)$phone->getID(),
                'glpi_lines.is_deleted'      => 0,
            ],
            'ORDER'  => 'glpi_lines.id',
            'LIMIT'  => 1,
        ]);

        if (!count($iterator)) {
            return null;
        }

        $row = $iterator->current();
        return $row['caller_num'] ?? null;
    }
}
