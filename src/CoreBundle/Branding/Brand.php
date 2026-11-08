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
use function strtolower;
use function trim;

/**
 * The name and logo an installation shows in place of SolidInvoice's own.
 *
 * Set per installation with SOLIDINVOICE_BRAND_NAME and SOLIDINVOICE_BRAND_LOGO (the path to a
 * PNG, JPEG, GIF, WebP or SVG file). With a brand name set the installation is white-labelled:
 * the "Powered by SolidInvoice" mentions are hidden everywhere.
 * @see \SolidInvoice\CoreBundle\Tests\Branding\BrandTest
 */
final class Brand
{
    private const array LOGO_TYPES = ['png' => 'png', 'jpg' => 'jpeg', 'jpeg' => 'jpeg', 'gif' => 'gif', 'webp' => 'webp', 'svg' => 'svg+xml'];

    private ?string $logo = null;

    private bool $logoLoaded = false;

    public function __construct(
        private readonly string $brandName = '',
        private readonly string $brandLogo = '',
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
        $type = self::LOGO_TYPES[strtolower(pathinfo($this->brandLogo, PATHINFO_EXTENSION))] ?? null;

        if ($type === null || ! is_file($this->brandLogo) || ! is_readable($this->brandLogo)) {
            return null;
        }

        $content = file_get_contents($this->brandLogo);

        return $this->logo = $content === false || $content === '' ? null : $type . '|' . base64_encode($content);
    }
}
