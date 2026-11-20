<?php

declare(strict_types=1);

/*
 * This file is part of SolidInvoice project.
 *
 * (c) Pierre du Plessis <open-source@solidworx.co>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SolidInvoice\CoreBundle\Branding;

use const PATHINFO_EXTENSION;
use SolidInvoice\CoreBundle\SolidInvoiceCoreBundle;
use function base64_encode;
use function file_get_contents;
use function is_file;
use function is_readable;
use function pathinfo;
use function preg_match;
use function strtolower;
use function trim;

/**
 * The name and logo an installation shows in place of SolidInvoice's own.
 *
 * Set per installation with SOLIDINVOICE_BRAND_NAME and SOLIDINVOICE_BRAND_LOGO (the path to a
 * PNG, JPEG, GIF, WebP or SVG file). With a brand name set the installation is white-labelled:
 * the "Powered by SolidInvoice" mentions are hidden everywhere, and when a logo is set as well the
 * signed-out pages show the logo alone, without the name next to it.
 *
 * SOLIDINVOICE_LOGIN_BACKGROUND (the path to a JPEG, PNG or WebP file) replaces the photo behind the
 * signed-out pages; SOLIDINVOICE_LOGIN_BACKGROUND_CREDIT and SOLIDINVOICE_LOGIN_BACKGROUND_CREDIT_URL
 * give its credit line, shown in place of the default photo's.
 * @see \SolidInvoice\CoreBundle\Tests\Branding\BrandTest
 */
final class Brand
{
    private const array LOGO_TYPES = ['png' => 'png', 'jpg' => 'jpeg', 'jpeg' => 'jpeg', 'gif' => 'gif', 'webp' => 'webp', 'svg' => 'svg+xml'];

    private ?string $logo = null;

    private const array BACKGROUND_TYPES = ['png' => 'png', 'jpg' => 'jpeg', 'jpeg' => 'jpeg', 'webp' => 'webp'];

    private bool $logoLoaded = false;

    private ?string $loginBackground = null;

    private bool $loginBackgroundLoaded = false;

    public function __construct(
        private readonly string $brandName = '',
        private readonly string $brandLogo = '',
        private readonly string $loginBackgroundFile = '',
        private readonly string $loginBackgroundCredit = '',
        private readonly string $loginBackgroundCreditUrl = '',
    ) {
    }

    public function isWhiteLabel(): bool
    {
        return trim($this->brandName) !== '';
    }

    public function getName(): string
    {
        return $this->isWhiteLabel() ? trim($this->brandName) : SolidInvoiceCoreBundle::APP_NAME;
    }

    /**
     * The logo as "type|base64", the format GlobalExtension::displayAppLogo() renders, or null when none is configured.
     */
    public function getLogo(): ?string
    {
        if ($this->logoLoaded) {
            return $this->logo;
        }

        $this->logoLoaded = true;
        $content = self::readImage($this->brandLogo, self::LOGO_TYPES);

        return $this->logo = $content === null ? null : $content[0] . '|' . $content[1];
    }

    /**
     * Whether the signed-out pages show the brand's logo alone: a white-label installation with a logo.
     */
    public function showsLogoOnly(): bool
    {
        return $this->isWhiteLabel() && $this->getLogo() !== null;
    }

    /**
     * The custom photo behind the signed-out pages as a data URI, or null to keep the default one.
     */
    public function getLoginBackground(): ?string
    {
        if ($this->loginBackgroundLoaded) {
            return $this->loginBackground;
        }

        $this->loginBackgroundLoaded = true;
        $content = self::readImage($this->loginBackgroundFile, self::BACKGROUND_TYPES);

        return $this->loginBackground = $content === null ? null : 'data:image/' . $content[0] . ';base64,' . $content[1];
    }

    public function getLoginBackgroundCredit(): string
    {
        return trim($this->loginBackgroundCredit);
    }

    /**
     * The credit link, only when it is an http(s) URL.
     */
    public function getLoginBackgroundCreditUrl(): string
    {
        $url = trim($this->loginBackgroundCreditUrl);

        return preg_match('#^https?://#i', $url) === 1 ? $url : '';
    }

    /**
     * @param array<string, string> $types
     *
     * @return array{string, string}|null the image type and its base64 content
     */
    private static function readImage(string $path, array $types): ?array
    {
        $type = $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if ($type === null || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false || $content === '' ? null : [$type, base64_encode($content)];
    }
}
