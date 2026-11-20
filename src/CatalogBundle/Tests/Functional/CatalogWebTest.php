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

namespace SolidInvoice\CatalogBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\CatalogBundle\Entity\CatalogItem;
use SolidInvoice\CatalogBundle\Test\Factory\CatalogItemFactory;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\UserBundle\Enum\UserSettingType;
use SolidInvoice\UserBundle\Repository\UserSettingRepositoryInterface;
use SolidInvoice\UserBundle\Test\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class CatalogWebTest extends WebTestCase
{
    use EnsureApplicationInstalled;

    public function testAddAnItem(): void
    {
        $client = $this->client();

        $crawler = $client->request(Request::METHOD_GET, '/items/add');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form();
        $form['catalog_item[name]'] = 'Monthly expenses';
        $form['catalog_item[description]'] = 'Car fuel';
        $form['catalog_item[price]'] = '2000.00';

        $client->submit($form);

        self::assertResponseRedirects('/items/');

        $item = self::getContainer()->get('doctrine')->getRepository(CatalogItem::class)->findOneBy(['name' => 'Monthly expenses']);
        self::assertInstanceOf(CatalogItem::class, $item);
        self::assertSame('Car fuel', $item->getDescription());
        self::assertTrue($item->getPrice()->isEqualTo(200000));
        self::assertNotSame('', $item->getCurrencyCode());
    }

    public function testListAndEditPagesRender(): void
    {
        $client = $this->client();
        $item = CatalogItemFactory::createOne(['company' => $this->company, 'name' => 'Commission for the sale of property', 'price' => 0]);

        foreach (['/items/', '/items/edit/' . $item->getId()] as $path) {
            $client->request(Request::METHOD_GET, $path);
            self::assertResponseIsSuccessful($path);
        }
    }

    private function client(): KernelBrowser
    {
        $user = UserFactory::createOne(['companies' => [$this->company]]);

        $settings = self::getContainer()->get(UserSettingRepositoryInterface::class);
        $settings->saveSetting($user, UserSettingType::OnboardComplete, 'true');
        $settings->saveSetting($user, UserSettingType::OnboardingChecklistDismissed, 'true');

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->loginUser($user);

        return $client;
    }
}
