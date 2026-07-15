<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Auth;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationTest extends AbstractApiTestCase
{
    private const URL = '/api/auth/inscription';

    public function testSuccessfulRegistration(): void
    {
        $this->postJson(self::URL, [
            'email' => 'nouveau@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('Compte créé. Vérifiez votre boîte e-mail pour activer votre compte.', $this->getJson()['message']);
    }

    public function testRegisteredUserIsNotVerifiedByDefault(): void
    {
        $this->postJson(self::URL, [
            'email' => 'nonverifie@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'nonverifie@example.com']);

        self::assertNotNull($user);
        self::assertFalse($user->isVerified());
        self::assertNotNull($user->getVerificationToken());
    }

    public function testRegistrationFailsWithMismatchedPasswords(): void
    {
        $this->postJson(self::URL, [
            'email' => 'test@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'different456',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRegistrationFailsWithShortPassword(): void
    {
        $this->postJson(self::URL, [
            'email' => 'test@example.com',
            'password' => 'court',
            'passwordConfirm' => 'court',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRegistrationFailsWithInvalidEmail(): void
    {
        $this->postJson(self::URL, [
            'email' => 'pas-un-email',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRegistrationFailsWithDuplicateEmail(): void
    {
        $this->createUser('existant@example.com');

        $this->postJson(self::URL, [
            'email' => 'existant@example.com',
            'password' => 'password123',
            'passwordConfirm' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRegistrationFailsWithMissingFields(): void
    {
        $this->postJson(self::URL, []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
