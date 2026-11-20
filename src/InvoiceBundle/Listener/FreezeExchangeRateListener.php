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

namespace SolidInvoice\InvoiceBundle\Listener;

use Brick\Math\RoundingMode;
use SolidInvoice\CoreBundle\Currency\ExchangeRateProvider;
use SolidInvoice\InvoiceBundle\Entity\Invoice;
use SolidInvoice\InvoiceBundle\Model\Graph;
use SolidInvoice\SettingsBundle\SystemConfig;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\Event;
use Throwable;

/**
 * Records, as an invoice in another currency is issued, what it is worth in the company's own
 * currency: the exchange rate of the invoice date. A tax invoice in a foreign currency must
 * state its value and VAT in the currency the VAT is declared in (in Thailand, baht).
 *
 * A rate the invoice already carries (given through the API, say the Bank of Thailand's) is kept.
 * The invoice is issued all the same when no rate can be found.
 *
 * @see \SolidInvoice\InvoiceBundle\Tests\Listener\FreezeExchangeRateListenerTest
 */
final readonly class FreezeExchangeRateListener
{
    public function __construct(
        private ExchangeRateProvider $provider,
        private SystemConfig $config,
    ) {
    }

    /**
     * @param Event<Invoice> $event
     */
    #[AsEventListener('workflow.invoice.transition.' . Graph::TRANSITION_ACCEPT)]
    public function __invoke(Event $event): void
    {
        $invoice = $event->getSubject();

        if (! $invoice instanceof Invoice || $invoice->getExchangeRate() !== null) {
            return;
        }

        try {
            $base = $this->config->getCurrency();
            $currency = $invoice->getCurrency();
        } catch (Throwable) {
            return;
        }

        if ($currency->equals($base)) {
            return;
        }

        $found = $this->provider->rate($currency, $base, $invoice->getInvoiceDate());

        if ($found === null) {
            return;
        }

        $invoice->setExchangeRate((string) $found['rate']->toScale(8, RoundingMode::HalfUp));
        $invoice->setExchangeRateCurrency($base->getCode());
        $invoice->setExchangeRateDate($found['date']);
        $invoice->setExchangeRateSource(ExchangeRateProvider::SOURCE);
    }
}
