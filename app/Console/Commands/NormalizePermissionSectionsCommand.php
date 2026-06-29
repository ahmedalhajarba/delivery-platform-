<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\PermissionSection;
use Illuminate\Console\Command;

class NormalizePermissionSectionsCommand extends Command
{
    protected $signature = 'permissions:normalize-sections {--dry-run : Preview changes without saving}';

    protected $description = 'Normalize permission_sections labels/domains and Arabicize remaining English labels.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $domainLabels = config('permission_domains.labels', []);

        $updated = 0;
        $unchanged = 0;

        $sections = PermissionSection::query()->get();

        if ($sections->isEmpty()) {
            $this->warn('لا توجد سجلات في جدول permission_sections.');
            return self::SUCCESS;
        }

        foreach ($sections as $section) {
            $normalizedName = $this->normalizeKey($section->name);
            $domain = Permission::resolveDomainKeyForSection($normalizedName);
            $targetLabel = $this->resolveArabicLabel($section, $normalizedName, $domain, $domainLabels);

            $needsUpdate = false;
            $payload = [];

            if ((string) $section->name !== $normalizedName && $normalizedName !== '') {
                $payload['name'] = $normalizedName;
                $needsUpdate = true;
            }

            if ((string) $section->domain !== $domain) {
                $payload['domain'] = $domain;
                $needsUpdate = true;
            }

            if ($targetLabel !== '' && (string) $section->label !== $targetLabel) {
                $payload['label'] = $targetLabel;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $updated++;
                $this->line(sprintf(
                    '[%s] id=%d | %s -> %s | domain: %s | label: %s',
                    $dryRun ? 'DRY' : 'OK',
                    (int) $section->id,
                    (string) $section->name,
                    (string) ($payload['name'] ?? $section->name),
                    (string) ($payload['domain'] ?? $section->domain),
                    (string) ($payload['label'] ?? $section->label)
                ));

                if (!$dryRun) {
                    $section->fill($payload)->save();
                }
            } else {
                $unchanged++;
            }
        }

        $this->newLine();
        $this->info('انتهى التطبيع بنجاح.');
        $this->line('المحدَّث: ' . $updated);
        $this->line('غير المتغيّر: ' . $unchanged);
        $this->line('الوضع: ' . ($dryRun ? 'معاينة فقط (--dry-run)' : 'تنفيذ فعلي'));

        return self::SUCCESS;
    }

    private function resolveArabicLabel($section, string $normalizedName, string $domain, array $domainLabels): string
    {
        $currentLabel = trim((string) $section->label);

        // 1) إذا التسمية الحالية عربية بالفعل نحتفظ بها.
        if ($currentLabel !== '' && $this->containsArabic($currentLabel)) {
            return $currentLabel;
        }

        // 2) استخدم mapping مخصص حسب اسم القسم.
        $mappedBySection = config('permission_sections.arabic_sections.' . $normalizedName);
        if (is_string($mappedBySection) && trim($mappedBySection) !== '') {
            return trim($mappedBySection);
        }

        // 3) fallback حسب domain label.
        $mappedByDomain = $domainLabels[$domain] ?? null;
        if (is_string($mappedByDomain) && trim($mappedByDomain) !== '') {
            return trim($mappedByDomain);
        }

        // 4) fallback قابل للقراءة (آخر حل).
        return Permission::sanitizeUiLabel(str_replace('_', ' ', $normalizedName));
    }

    private function normalizeKey(?string $key): string
    {
        $k = strtolower(trim((string) $key));
        $k = str_replace(['-', ' '], '_', $k);
        $k = preg_replace('/[^a-z0-9_]+/', '', $k) ?: '';
        $k = preg_replace('/_+/', '_', $k) ?: '';
        return trim($k, '_');
    }

    private function containsArabic(string $value): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $value) === 1;
    }
}
