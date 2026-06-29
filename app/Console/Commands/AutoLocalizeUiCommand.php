<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutoLocalizeUiCommand extends Command
{
    protected $signature = 'i18n:auto-localize-ui
                            {--path=resources/views : Base path to scan}
                            {--write : Apply replacements to Blade files}
                            {--check : Return non-zero exit code if localizable hardcoded texts are found}
                            {--include-js : Also localize JS string literals that contain Arabic text}';

    protected $description = 'Auto-localize hardcoded UI text in forms/tables and sync resources/lang/{ar,en}/auto.php';

    protected array $dictionary = [
        'إضافة' => 'Add',
        'تعديل' => 'Edit',
        'حذف' => 'Delete',
        'إلغاء' => 'Cancel',
        'عودة' => 'Back',
        'حفظ' => 'Save',
        'تحديث' => 'Update',
        'عرض' => 'View',
        'الحالة' => 'Status',
        'التاريخ' => 'Date',
        'الاسم' => 'Name',
        'الإجراءات' => 'Actions',
        'إجراءات' => 'Actions',
        'الفرع' => 'Branch',
        'المدينة' => 'City',
        'المحافظة' => 'Governorate',
        'الحي' => 'Neighborhood',
        'ملاحظات' => 'Notes',
        'الرقم' => 'Number',
        'النوع' => 'Type',
        'من' => 'From',
        'إلى' => 'To',
        'كل الفروع' => 'All branches',
        'كل الحالات' => 'All statuses',
        'تطبيق الفلتر' => 'Apply filter',
    ];

    public function handle(): int
    {
        $basePath = base_path(trim((string) $this->option('path')));
        if (!is_dir($basePath)) {
            $this->error('Path not found: ' . $basePath);
            return self::FAILURE;
        }

        $write = (bool) $this->option('write');
        $check = (bool) $this->option('check');
        $includeJs = (bool) $this->option('include-js');

        $files = $this->collectBladeFiles($basePath);
        $this->info('Scanning files: ' . count($files));

        $ar = $this->loadLangFile(base_path('resources/lang/ar/auto.php'));
        $en = $this->loadLangFile(base_path('resources/lang/en/auto.php'));

        $totalReplacements = 0;
        $changedFiles = 0;

        foreach ($files as $filePath) {
            $original = file_get_contents($filePath);
            if ($original === false) {
                continue;
            }

            $updated = $this->localizeBladeContent($original, $ar, $en, $includeJs, $totalReplacements);

            if ($updated !== $original) {
                $changedFiles++;
                if ($write) {
                    file_put_contents($filePath, $updated);
                }
            }
        }

        if ($write) {
            $this->writeLangFile(base_path('resources/lang/ar/auto.php'), $ar);
            $this->writeLangFile(base_path('resources/lang/en/auto.php'), $en);
        }

        $this->line('Replacements: ' . $totalReplacements);
        $this->line('Changed files: ' . $changedFiles . ($write ? ' (written)' : ' (dry-run)'));
        if ($write) {
            $this->line('Synced language files: resources/lang/ar/auto.php, resources/lang/en/auto.php');
        }

        if ($check && $changedFiles > 0) {
            $this->error('Hardcoded localizable texts found. Run: php artisan i18n:auto-localize-ui --write');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function collectBladeFiles(string $basePath): array
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }

            $path = $file->getPathname();
            if (!Str::endsWith($path, '.blade.php')) {
                continue;
            }

            if (Str::contains($path, '__MACOSX')) {
                continue;
            }

            $files[] = $path;
        }

        return $files;
    }

    private function localizeBladeContent(string $content, array &$ar, array &$en, bool $includeJs, int &$totalReplacements): string
    {
        $tagPattern = '/<(label|th|button|option|a|h1|h2|h3|h4|h5|h6|span|td)([^>]*)>([^<\{\}@]+)<\/\\1>/u';
        $attrPattern = '/\s(placeholder|title|aria-label)="([^"\{\}@]+)"/u';
        $jsPattern = '/([\'\"])([^\'\"\\n\r]*[\x{0600}-\x{06FF}][^\'\"\\n\r]*)\1/u';

        $content = preg_replace_callback($tagPattern, function ($m) use (&$ar, &$en, &$totalReplacements) {
            $tag = $m[1];
            $attrs = $m[2];
            $text = trim($m[3]);

            if (!$this->isLocalizableLiteral($text)) {
                return $m[0];
            }

            $key = $this->ensureAutoKey($text, $ar, $en);
            $totalReplacements++;

            return '<' . $tag . $attrs . '>{{ __(\'auto.' . $key . '\') }}</' . $tag . '>';
        }, $content) ?? $content;

        $content = preg_replace_callback($attrPattern, function ($m) use (&$ar, &$en, &$totalReplacements) {
            $attr = $m[1];
            $text = trim($m[2]);

            if (!$this->isLocalizableLiteral($text)) {
                return $m[0];
            }

            $key = $this->ensureAutoKey($text, $ar, $en);
            $totalReplacements++;

            return ' ' . $attr . '="{{ __(\'auto.' . $key . '\') }}"';
        }, $content) ?? $content;

        if ($includeJs) {
            $content = preg_replace_callback($jsPattern, function ($m) use (&$ar, &$en, &$totalReplacements) {
                $quote = $m[1];
                $text = trim($m[2]);

                if (!$this->isLocalizableLiteral($text)) {
                    return $m[0];
                }

                $key = $this->ensureAutoKey($text, $ar, $en);
                $totalReplacements++;

                return $quote . "{{ __('auto." . $key . "') }}" . $quote;
            }, $content) ?? $content;
        }

        return $content;
    }

    private function isLocalizableLiteral(string $text): bool
    {
        if ($text === '' || Str::contains($text, ['{{', '}}', '@lang', 'trans(', '__('])) {
            return false;
        }

        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return false;
        }

        return true;
    }

    private function ensureAutoKey(string $text, array &$ar, array &$en): string
    {
        $existing = array_search($text, $ar, true);
        if ($existing !== false) {
            if (!isset($en[$existing])) {
                $en[$existing] = $this->dictionary[$text] ?? $text;
            }
            return (string) $existing;
        }

        $base = 'k_' . substr(md5($text), 0, 10);
        $key = $base;
        $counter = 1;

        while (isset($ar[$key]) && $ar[$key] !== $text) {
            $key = $base . '_' . $counter;
            $counter++;
        }

        $ar[$key] = $text;
        $en[$key] = $this->dictionary[$text] ?? $text;

        return $key;
    }

    private function loadLangFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $data = include $path;
        return is_array($data) ? $data : [];
    }

    private function writeLangFile(string $path, array $data): void
    {
        ksort($data);
        $export = var_export($data, true);
        $php = "<?php\n\nreturn " . $export . ";\n";
        file_put_contents($path, $php);
    }
}
