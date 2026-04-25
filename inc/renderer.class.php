<?php
declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Direct access not allowed');
}

class PluginPhonebgRenderer
{
    private static ?\Twig\Environment $twig = null;

    private static function getTwig(): \Twig\Environment
    {
        if (self::$twig === null) {
            $loader = new \Twig\Loader\FilesystemLoader(
                Plugin::getPhpDir('phonebg') . '/templates'
            );
            $env = new \Twig\Environment($loader, [
                'cache'       => GLPI_CACHE_DIR . '/twig_phonebg',
                'auto_reload' => true,
                'autoescape'  => 'html',
            ]);
            $env->addFunction(new \Twig\TwigFunction('__', '__'));
            self::$twig = $env;
        }
        return self::$twig;
    }

    public static function display(string $template, array $vars = []): void
    {
        echo self::getTwig()->render($template, $vars);
    }

    public static function render(string $template, array $vars = []): string
    {
        return self::getTwig()->render($template, $vars);
    }
}
