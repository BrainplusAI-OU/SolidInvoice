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

namespace SolidInvoice\AssistantBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use SolidInvoice\AssistantBundle\Entity\Assistant;
use SolidInvoice\AssistantBundle\Entity\AssistantMember;
use SolidInvoice\AssistantBundle\Manager\EventRecorder;
use SolidInvoice\AssistantBundle\Test\Factory\AssistantFactory;
use SolidInvoice\AssistantBundle\Test\Factory\AssistantMemberFactory;
use SolidInvoice\CoreBundle\Company\CompanySelector;
use SolidInvoice\InstallBundle\Test\EnsureApplicationInstalled;
use SolidInvoice\UserBundle\Enum\UserSettingType;
use SolidInvoice\UserBundle\Repository\UserSettingRepositoryInterface;
use SolidInvoice\UserBundle\Test\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;

#[Group('functional')]
final class AssistantWebTest extends WebTestCase
{
    use EnsureApplicationInstalled;

    public function testAddAnAssistant(): void
    {
        $client = $this->client();

        $crawler = $client->request(Request::METHOD_GET, '/assistants/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No assistant yet');

        $crawler = $client->request(Request::METHOD_GET, '/assistants/add');
        $form = $crawler->selectButton('Save')->form();
        $form['assistant[name]'] = 'Jeannine';
        $form['assistant[inboxEmail]'] = 'accounting@example.com';
        $client->submit($form);

        $assistant = self::getContainer()->get('doctrine')->getRepository(Assistant::class)->findOneBy(['name' => 'Jeannine']);
        self::assertInstanceOf(Assistant::class, $assistant);
        self::assertResponseRedirects('/assistants/' . $assistant->getId() . '/settings');

        // With a single assistant, the list goes straight to it, and the menu names it.
        $client->request(Request::METHOD_GET, '/assistants/');
        self::assertResponseRedirects('/assistants/' . $assistant->getId());
    }

    public function testEveryTabRenders(): void
    {
        $client = $this->client();
        $assistant = AssistantFactory::createOne(['company' => $this->company, 'name' => 'Jeannine']);
        $member = AssistantMemberFactory::createOne(['company' => $this->company, 'assistant' => $assistant, 'name' => 'Rose', 'externalId' => '42']);

        self::getContainer()->get(CompanySelector::class)->switchCompany($this->company->getId());
        $recorder = self::getContainer()->get(EventRecorder::class);
        $recorder->record($assistant, ['key' => 'k1', 'type' => 'document', 'subject' => '<b>Makro</b> 1,250 THB', 'member' => ['externalId' => '42'], 'links' => [['label' => 'Drive', 'url' => 'https://drive.example.com/x'], ['label' => 'bad', 'url' => 'javascript:alert(1)']]]);
        $recorder->record($assistant, ['type' => 'message', 'direction' => 'in', 'subject' => 'Bonjour', 'member' => ['externalId' => '42']]);
        $recorder->record($assistant, ['type' => 'email', 'subject' => 'Facture octobre', 'body' => 'From: supplier@example.com']);
        $recorder->record($assistant, ['type' => 'access', 'subject' => '/start', 'member' => ['externalId' => '9', 'name' => 'Stranger']]);

        $id = (string) $assistant->getId();

        foreach (['', '/activity', '/conversations', '/emails', '/members', '/members/add', '/members/' . $member->getId() . '/edit', '/settings'] as $path) {
            $client->request(Request::METHOD_GET, '/assistants/' . $id . $path);
            self::assertResponseIsSuccessful($path);
        }

        $client->request(Request::METHOD_GET, '/assistants/' . $id);
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Jeannine', $html);
        self::assertStringContainsString('&lt;b&gt;Makro&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Makro</b>', $html);
        self::assertStringNotContainsString('javascript:alert', $html);
    }

    public function testShutOutAMember(): void
    {
        $client = $this->client();
        $assistant = AssistantFactory::createOne(['company' => $this->company]);
        $member = AssistantMemberFactory::createOne(['company' => $this->company, 'assistant' => $assistant, 'name' => 'Adrien', 'externalId' => '55']);

        $crawler = $client->request(Request::METHOD_GET, '/assistants/' . $assistant->getId() . '/members/' . $member->getId() . '/edit');
        $form = $crawler->selectButton('Save')->form();
        $active = $form['assistant_member[active]'];
        self::assertInstanceOf(ChoiceFormField::class, $active);
        $active->untick();
        $client->submit($form);

        self::assertResponseRedirects('/assistants/' . $assistant->getId() . '/members');

        $reloaded = self::getContainer()->get('doctrine')->getRepository(AssistantMember::class)->find($member->getId());
        self::assertFalse($reloaded?->isActive());
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
