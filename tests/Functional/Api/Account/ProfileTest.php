<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Account;

use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProfileTest extends AbstractApiTestCase
{
    public function testUpdateRequiresAuthentication(): void
    {
        $this->putJson('/api/compte/profil', ['firstName' => 'Jean', 'lastName' => 'Dupont']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUpdateProfile(): void
    {
        $this->loginAs($this->createUser());

        $this->putJson('/api/compte/profil', [
            'firstName' => 'Jean',
            'lastName'  => 'Dupont',
            'phone'     => '0102030405',
        ]);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame('Jean', $data['firstName']);
        self::assertSame('Dupont', $data['lastName']);
        self::assertSame('0102030405', $data['phone']);
        self::assertTrue($data['hasProfile']);
    }

    public function testUpdateRejectsBlankName(): void
    {
        $this->loginAs($this->createUser());

        $this->putJson('/api/compte/profil', ['firstName' => 'Jean', 'lastName' => '   ']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePassword(): void
    {
        $this->loginAs($this->createUser(password: 'password123'));

        $this->putJson('/api/compte/mot-de-passe', [
            'currentPassword'    => 'password123',
            'newPassword'        => 'nouveauSecret123',
            'newPasswordConfirm' => 'nouveauSecret123',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testChangePasswordRejectsWrongCurrent(): void
    {
        $this->loginAs($this->createUser(password: 'password123'));

        $this->putJson('/api/compte/mot-de-passe', [
            'currentPassword'    => 'mauvais',
            'newPassword'        => 'nouveauSecret123',
            'newPasswordConfirm' => 'nouveauSecret123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePasswordRejectsTooShort(): void
    {
        $this->loginAs($this->createUser(password: 'password123'));

        $this->putJson('/api/compte/mot-de-passe', [
            'currentPassword'    => 'password123',
            'newPassword'        => 'court',
            'newPasswordConfirm' => 'court',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePasswordRejectsMismatch(): void
    {
        $this->loginAs($this->createUser(password: 'password123'));

        $this->putJson('/api/compte/mot-de-passe', [
            'currentPassword'    => 'password123',
            'newPassword'        => 'nouveauSecret123',
            'newPasswordConfirm' => 'autreSecret123',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
