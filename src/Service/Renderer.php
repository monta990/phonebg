<?php

declare(strict_types=1);

namespace GlpiPlugin\Phonebg\Service;

use Glpi\Application\View\TemplateRenderer;

final class Renderer
{
   public static function render(string $template, array $vars = []): string
   {
      ob_start();
      try {
         TemplateRenderer::getInstance()->display(str_starts_with($template, '@') ? $template : '@phonebg/' . ltrim($template, '/'), $vars);
         return (string) ob_get_clean();
      } catch (\Throwable $e) {
         ob_end_clean();
         throw $e;
      }
   }

   public static function display(string $template, array $vars = []): void
   {
      TemplateRenderer::getInstance()->display(str_starts_with($template, '@') ? $template : '@phonebg/' . ltrim($template, '/'), $vars);
   }
}
