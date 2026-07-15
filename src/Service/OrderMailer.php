<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OrderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
        #[Autowire(param: 'app.company')]
        private readonly array $company,
    ) {
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $customer = $order->getCustomer();

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from, $this->company['name']))
                ->to(new Address($customer->getEmail(), $customer->getFirstName().' '.$customer->getLastName()))
                ->subject(sprintf('Confirmation de votre commande %s', $order->getOrderNumber()))
                ->htmlTemplate('emails/order_confirmation.html.twig')
                ->context([
                    'order'   => $order,
                    'company' => $this->company,
                ]),
        );
    }

    public function sendOrderShipped(Order $order): void
    {
        $customer = $order->getCustomer();

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from, $this->company['name']))
                ->to(new Address($customer->getEmail(), $customer->getFirstName().' '.$customer->getLastName()))
                ->subject(sprintf('Votre commande %s est en chemin !', $order->getOrderNumber()))
                ->htmlTemplate('emails/order_shipped.html.twig')
                ->context([
                    'order'   => $order,
                    'company' => $this->company,
                ]),
        );
    }
}
