<?php

namespace App\Support;

final class ServiceIcon
{
    public const DEFAULT = 'material:local_laundry_service_outlined';

    /**
     * @var array<string, string>
     */
    private const GLYPHS = [
        'material:cleaning_services_outlined' => 'M341 277H320V448C320 471 301 491 277 491H235C211 491 192 471 192 448V277H171C112 277 64 230 64 171V21H448V171C448 230 400 277 341 277ZM235 448H277V277H235V448ZM405 64H363V128C363 140 353 149 341 149C330 149 320 140 320 128V64H277V128C277 140 268 149 256 149C244 149 235 140 235 128V64H192V128C192 140 182 149 171 149C159 149 149 140 149 128V64H107V171C107 206 135 235 171 235H341C377 235 405 206 405 171V64Z',
        'material:air_outlined' => 'M309 149C309 114 281 85 245 85C210 85 181 114 181 149H224C224 138 234 128 245 128C257 128 267 138 267 149C267 161 257 171 245 171H43V213H245C281 213 309 185 309 149ZM405 373C405 415 372 448 331 448C289 448 256 415 256 373H299C299 391 313 405 331 405C348 405 363 391 363 373C363 356 348 341 331 341H43V299H331C372 299 405 332 405 373ZM395 277H43V235H395C412 235 427 220 427 203C427 185 412 171 395 171V128C436 128 469 161 469 203C469 244 436 277 395 277Z',
        'material:dry_cleaning_outlined' => 'M417 270 277 332V363C277 374 268 384 256 384C244 384 235 394 235 405C235 417 244 427 256 427C268 427 277 417 277 405H320C320 445 285 476 244 468C219 464 198 443 193 418C187 385 206 355 235 345V332L95 269C76 261 64 243 64 223V222C64 194 87 171 116 171H149V43H363V171H396C425 171 448 194 448 222V223C448 243 436 261 417 270ZM320 85H192V192H320V85ZM396 213H363V235H149V213H116C106 213 103 227 112 231L256 295L400 231C409 227 406 213 396 213Z',
        'material:iron_outlined' => 'M448 384C413 384 384 355 384 320V235C384 223 374 213 363 213V299C363 334 334 363 299 363H213C178 363 149 334 149 299H192C192 310 202 320 213 320H299C310 320 320 310 320 299V277H128C81 277 43 239 43 192V128H363V171C398 171 427 199 427 235V320C427 332 436 341 448 341H469V384H448ZM320 171H85V192C85 215 105 235 128 235H320V171Z',
        'material:water_drop_outlined' => 'M256 469C142 372 85 288 85 218C85 111 166 43 256 43C346 43 427 111 427 218C427 288 370 372 256 469ZM256 85C185 85 128 140 128 218C128 268 170 334 256 413C342 334 384 268 384 218C384 140 327 85 256 85ZM167 213C175 213 181 208 183 200C192 153 231 137 260 139C270 139 277 132 277 123C277 114 271 107 262 107C217 104 163 130 151 195C150 204 157 213 167 213Z',
        'material:local_laundry_service_outlined' => 'M384 469H128C104 469 85 450 85 427V85C85 62 104 43 128 43H384C408 43 427 62 427 85V427C427 450 408 469 384 469ZM384 85H128V427H384V85ZM149 384C149 396 159 405 171 405C182 405 192 396 192 384C192 372 182 363 171 363C159 363 149 372 149 384ZM213 384C213 396 223 405 235 405C246 405 256 396 256 384C256 372 246 363 235 363C223 363 213 372 213 384ZM256 107C315 107 363 154 363 213C363 272 315 320 256 320C197 320 149 272 149 213C149 154 197 107 256 107ZM306 264C334 236 334 191 306 163C279 135 233 135 206 163L306 264Z',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'material:cleaning_services_outlined' => 'Tẩy trắng',
            'material:air_outlined' => 'Sấy khô',
            'material:dry_cleaning_outlined' => 'Giặt khô',
            'material:iron_outlined' => 'Ủi',
            'material:water_drop_outlined' => 'Giặt thường',
            'material:local_laundry_service_outlined' => 'Giặt đồ',
        ];
    }

    public static function resolve(
        ?string $icon,
        ?string $serviceName = null,
        ?string $categoryName = null,
    ): string {
        if ($icon !== null && isset(self::GLYPHS[$icon])) {
            return $icon;
        }

        foreach ([$categoryName, $serviceName] as $label) {
            $resolved = self::resolveLabel($label);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return self::DEFAULT;
    }

    public static function glyph(string $icon): ?string
    {
        return self::GLYPHS[$icon] ?? null;
    }

    private static function resolveLabel(?string $label): ?string
    {
        $normalizedName = mb_strtolower($label ?? '');
        $fallbacks = [
            'sấy' => 'material:air_outlined',
            'khô' => 'material:dry_cleaning_outlined',
            'tẩy' => 'material:cleaning_services_outlined',
            'ủi' => 'material:iron_outlined',
            'giặt' => 'material:water_drop_outlined',
        ];

        foreach ($fallbacks as $keyword => $fallback) {
            if (str_contains($normalizedName, $keyword)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array{color: string, background: string}
     */
    public static function colors(string $icon): array
    {
        return match ($icon) {
            'material:cleaning_services_outlined' => ['color' => '#0D9488', 'background' => 'rgba(13, 148, 136, 0.12)'],
            'material:air_outlined' => ['color' => '#10B981', 'background' => 'rgba(16, 185, 129, 0.12)'],
            'material:dry_cleaning_outlined' => ['color' => '#0284C7', 'background' => 'rgba(2, 132, 199, 0.12)'],
            'material:iron_outlined' => ['color' => '#F59E0B', 'background' => 'rgba(245, 158, 11, 0.12)'],
            'material:water_drop_outlined' => ['color' => '#06B6D4', 'background' => 'rgba(6, 182, 212, 0.12)'],
            default => ['color' => '#3B82F6', 'background' => '#DBEAFE'],
        };
    }
}
