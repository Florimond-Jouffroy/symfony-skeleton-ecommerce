<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        $verificationUrl = $this->urlGenerator->generate(
            'app_security_verify_email',
            ['token' => $user->getVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from))
                ->to(new Address($user->getEmail()))
                ->subject('Confirmez votre adresse e-mail')
                ->htmlTemplate('emails/email_verification.html.twig')
                ->context(['verificationUrl' => $verificationUrl]),
        );
    }

    public function sendPasswordResetCode(User $user, string $code): void
    {
        $this->mailer->send(
            (new TemplatedEmail())
                ->from(new Address($this->from))
                ->to(new Address($user->getEmail()))
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context(['code' => $code]),
        );
    }
}
