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

namespace SolidInvoice\CoreBundle\DependencyInjection\Compiler;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives the platform the brand name of a white-label installation.
 *
 * The platform layouts (page titles, the default footer) and the two-factor issuer read the
 * application name from `solidworx_platform.app.name`, which PlatformBundle sets from
 * platform.yaml. This runs after the extensions are loaded so it overrides that value; the
 * env processor falls back to the original name when SOLIDINVOICE_BRAND_NAME is empty.
 */
final class BrandNamePass implements CompilerPassInterface
{
    private const string APP_NAME_PARAMETER = 'solidworx_platform.app.name';

    private const string DEFAULT_NAME_PARAMETER = 'solidinvoice.brand.default_name';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (! $container->hasParameter(self::APP_NAME_PARAMETER)) {
            return;
        }

        $container->setParameter(self::DEFAULT_NAME_PARAMETER, $container->getParameter(self::APP_NAME_PARAMETER));
        $container->setParameter(self::APP_NAME_PARAMETER, '%env(default:' . self::DEFAULT_NAME_PARAMETER . ':SOLIDINVOICE_BRAND_NAME)%');
    }
}
