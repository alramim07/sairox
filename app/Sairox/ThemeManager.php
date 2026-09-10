<?php

namespace App\Sairox;

use Illuminate\Support\Facades\File;

class ThemeManager
{
    protected ?array $activeTheme = null;

    public function getActiveTheme(): ?array
    {
        if ($this->activeTheme) {
            return $this->activeTheme;
        }

        $themeSlug = get_option('active_theme', 'sairox-default');
        $path = base_path("themes/{$themeSlug}");

        if (! is_dir($path)) {
            return null;
        }

        $manifest = json_decode(file_get_contents("{$path}/theme.json"), true);

        if (! $manifest) {
            return null;
        }

        $this->activeTheme = array_merge($manifest, [
            'path' => $path,
            'slug' => $themeSlug,
        ]);

        return $this->activeTheme;
    }

    public function getThemeView(string $view): string
    {
        $themePath = $this->getActiveTheme()['path'] ?? null;

        if ($themePath && File::exists("{$themePath}/views/{$view}.blade.php")) {
            return "theme::{$view}";
        }

        return "sairox-core::{$view}";
    }

    public function getPostContentView(string $format, ?string $themePath = null): string
    {
        $path = $themePath ?? $this->getActiveTheme()['path'] ?? null;

        if ($path !== null) {
            if ($format !== '' && $format !== 'standard' && File::exists("{$path}/views/partials/content-{$format}.blade.php")) {
                return "theme::partials.content-{$format}";
            }

            if (File::exists("{$path}/views/partials/content-standard.blade.php")) {
                return 'theme::partials.content-standard';
            }
        }

        return 'sairox-core::partials.content-standard';
    }

    public function getInstalledThemes(): array
    {
        $themes = [];
        $themeDir = base_path('themes');

        if (! is_dir($themeDir)) {
            return $themes;
        }

        foreach (File::directories($themeDir) as $dir) {
            $manifestPath = "{$dir}/theme.json";
            if (File::exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                if ($manifest) {
                    $themes[] = array_merge($manifest, [
                        'path' => $dir,
                        'slug' => basename($dir),
                    ]);
                }
            }
        }

        return $themes;
    }

    public function getActiveThemeSlug(): string
    {
        return $this->getActiveTheme()['slug'] ?? 'sairox-default';
    }
}
