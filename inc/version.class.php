<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
   die('Direct access not allowed');
}

/**
 * GitHub release checker for phonebg.
 *
 * The checker is deliberately isolated from the plugin configuration table:
 * a GitHub/network failure can never affect the normal plugin configuration.
 */
class PluginPhonebgVersion {

   public const CURRENT_VERSION = '1.5.7';
   private const RELEASES_URL   = 'https://github.com/monta990/phonebg/releases';
   private const API_URL        = 'https://api.github.com/repos/monta990/phonebg/releases/latest';
   private const CACHE_TTL      = 21600; // 6 hours
   private const MAX_RESPONSE   = 65536; // 64 KiB

   public static function getStatus(bool $forceRefresh = false): array
   {
      $cached = self::readCache();
      if (!$forceRefresh && $cached !== null && (time() - (int)($cached['checked_at'] ?? 0)) < self::CACHE_TTL) {
         return self::buildStatus($cached, true);
      }

      $remote = self::fetchLatestStableRelease();
      if ($remote !== null) {
         self::writeCache($remote);
         return self::buildStatus($remote, false);
      }

      // GitHub unavailable: keep the last known good result instead of turning
      // a transient network error into a false "up to date" state.
      if ($cached !== null && !empty($cached['latest_version'])) {
         $status = self::buildStatus($cached, true);
         $status['github_available'] = false;
         return $status;
      }

      return [
         'current_version'  => self::CURRENT_VERSION,
         'latest_version'   => null,
         'update_available' => false,
         'github_available' => false,
         'from_cache'       => false,
         'checked_at'       => null,
         'releases_url'     => self::RELEASES_URL,
      ];
   }

   private static function buildStatus(array $data, bool $fromCache): array
   {
      $latest = isset($data['latest_version']) ? ltrim((string)$data['latest_version'], 'vV') : null;

      return [
         'current_version'  => self::CURRENT_VERSION,
         'latest_version'   => $latest,
         'update_available' => $latest !== null && $latest !== ''
            && version_compare($latest, self::CURRENT_VERSION, '>'),
         'github_available' => true,
         'from_cache'       => $fromCache,
         'checked_at'       => isset($data['checked_at']) ? (int)$data['checked_at'] : null,
         'releases_url'     => self::RELEASES_URL,
      ];
   }

   private static function fetchLatestStableRelease(): ?array
   {
      if (!function_exists('curl_init')) {
         return null;
      }

      $ch = curl_init(self::API_URL);
      if ($ch === false) {
         return null;
      }

      $body = '';
      curl_setopt_array($ch, [
         CURLOPT_RETURNTRANSFER => false,
         CURLOPT_FOLLOWLOCATION => false,
         CURLOPT_CONNECTTIMEOUT => 2,
         CURLOPT_TIMEOUT        => 4,
         CURLOPT_USERAGENT      => 'GLPI-phonebg/' . self::CURRENT_VERSION,
         CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json'],
         CURLOPT_WRITEFUNCTION  => static function ($curl, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE) {
               return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
         },
      ]);

      $ok       = curl_exec($ch);
      $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($ok === false || $httpCode !== 200 || $body === '') {
         return null;
      }

      try {
         $releases = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
      } catch (Throwable) {
         return null;
      }

      if (!is_array($releases)
          || !empty($releases['draft'])
          || !empty($releases['prerelease'])
          || empty($releases['tag_name'])) {
         return null;
      }

      $version = ltrim((string)$releases['tag_name'], 'vV');
      if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
         return null;
      }

      return [
         'latest_version' => $version,
         'checked_at'     => time(),
      ];
   }

   private static function cacheFile(): string
   {
      $base = defined('GLPI_CACHE_DIR') ? GLPI_CACHE_DIR : GLPI_VAR_DIR . '/_cache';
      return rtrim($base, '/\\') . '/phonebg/release_cache.json';
   }

   private static function readCache(): ?array
   {
      $file = self::cacheFile();
      if (!is_readable($file)) {
         return null;
      }

      $raw = @file_get_contents($file);
      if ($raw === false || strlen($raw) > 4096) {
         return null;
      }

      try {
         $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
      } catch (Throwable) {
         return null;
      }

      return is_array($data) ? $data : null;
   }

   private static function writeCache(array $data): void
   {
      $file = self::cacheFile();
      $dir  = dirname($file);
      if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
         return;
      }

      $json = json_encode($data, JSON_UNESCAPED_SLASHES);
      if ($json === false) {
         return;
      }

      $tmp = @tempnam($dir, 'release_');
      if ($tmp === false) {
         return;
      }

      if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $file)) {
         @unlink($tmp);
         return;
      }
      @chmod($file, 0644);
   }
}
