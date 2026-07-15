<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Admin;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserManagementTest extends AbstractApiTestCase
{
    // ── Contrôle d'accès ──────────────────────────────────────────────────────

    public function testListRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListForbiddenForNonAdmin(): void
    {
        $this->loginAs($this->createUser('simple@example.com'));

        $this->client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testResetPasswordForbiddenForNonAdmin(): void
    {
        $target = $this->createUser('cible@example.com');
        $this->loginAs($this->createUser('simple@example.com'));

        $this->postJson('/api/admin/utilisateurs/'.$target->getId().'/reinitialiser-mot-de-passe', []);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testVerifyForbiddenForNonAdmin(): void
    {
        $target = $this->createUser('cible@example.com', verified: false);
        $this->loginAs($this->createUser('simple@example.com'));

        $this->postJson('/api/admin/utilisateurs/'.$target->getId().'/verifier', []);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testResendVerificationForbiddenForNonAdmin(): void
    {
        $target = $this->createUser('cible@example.com', verified: false);
        $this->loginAs($this->createUser('simple@example.com'));

        $this->postJson('/api/admin/utilisateurs/'.$target->getId().'/renvoyer-verification', []);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUpdateRolesForbiddenForNonAdmin(): void
    {
        $target = $this->createUser('cible@example.com');
        $this->loginAs($this->createUser('simple@example.com'));

        $this->putJson('/api/admin/utilisateurs/'.$target->getId().'/roles', ['roles' => ['ROLE_ADMIN']]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $target = $this->createUser('cible@example.com');
        $this->loginAs($this->createUser('simple@example.com'));

        $this->client->request('DELETE', '/api/admin/utilisateurs/'.$target->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Liste ─────────────────────────────────────────────────────────────────

    public function testListReturnsUsersWithPagination(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');

        $this->client->request('GET', '/api/admin/utilisateurs');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(3, $data['total']);
        self::assertCount(3, $data['items']);
        self::assertArrayHasKey('email', $data['items'][0]);
        self::assertArrayHasKey('roles', $data['items'][0]);
        self::assertArrayHasKey('isVerified', $data['items'][0]);
    }

    public function testListFiltersByEmail(): void
    {
        $this->loginAs($this->createAdmin());
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');

        $this->client->request('GET', '/api/admin/utilisateurs', ['q' => 'alice']);

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['total']);
        self::assertSame('alice@example.com', $data['items'][0]['email']);
    }

    // ── Réinitialisation de mot de passe ──────────────────────────────────────

    public function testResetPasswordSendsCode(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('cible@example.com');

        $this->postJson('/api/admin/utilisateurs/'.$user->getId().'/reinitialiser-mot-de-passe', []);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
        self::assertNotNull(
            $this->em->getRepository(PasswordResetToken::class)->findOneBy(['user' => $user]),
        );
    }

    // ── Vérification e-mail ───────────────────────────────────────────────────

    public function testVerifyMarksUserAsVerified(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('nonverifie@example.com', verified: false);

        $this->postJson('/api/admin/utilisateurs/'.$user->getId().'/verifier', []);

        self::assertResponseIsSuccessful();
        $this->refreshUser($user);
        self::assertTrue($user->isVerified());
        self::assertNull($user->getVerificationToken());
    }

    public function testVerifyRejectsAlreadyVerifiedUser(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('verifie@example.com', verified: true);

        $this->postJson('/api/admin/utilisateurs/'.$user->getId().'/verifier', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testResendVerificationForUnverifiedUser(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('nonverifie@example.com', verified: false);

        $this->postJson('/api/admin/utilisateurs/'.$user->getId().'/renvoyer-verification', []);

        self::assertResponseIsSuccessful();
        self::assertEmailCount(1);
    }

    public function testResendVerificationRejectsVerifiedUser(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('verifie@example.com', verified: true);

        $this->postJson('/api/admin/utilisateurs/'.$user->getId().'/renvoyer-verification', []);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── Gestion des rôles ─────────────────────────────────────────────────────

    public function testPromoteUserToAdmin(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('cible@example.com');

        $this->putJson('/api/admin/utilisateurs/'.$user->getId().'/roles', ['roles' => ['ROLE_ADMIN']]);

        self::assertResponseIsSuccessful();
        $this->refreshUser($user);
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testDemoteAdminToUser(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createAdmin('autre-admin@example.com');

        $this->putJson('/api/admin/utilisateurs/'.$user->getId().'/roles', ['roles' => []]);

        self::assertResponseIsSuccessful();
        $this->refreshUser($user);
        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testRolesIgnoresUnknownRoles(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('cible@example.com');

        $this->putJson('/api/admin/utilisateurs/'.$user->getId().'/roles', ['roles' => ['ROLE_SUPER_ADMIN', 'ROLE_HACK']]);

        self::assertResponseIsSuccessful();
        $this->refreshUser($user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testCannotChangeOwnRoles(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin);

        $this->putJson('/api/admin/utilisateurs/'.$admin->getId().'/roles', ['roles' => []]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->refreshUser($admin);
        self::assertContains('ROLE_ADMIN', $admin->getRoles());
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteUser(): void
    {
        $this->loginAs($this->createAdmin());
        $user = $this->createUser('a-supprimer@example.com');
        $userId = $user->getId();

        $this->client->request('DELETE', '/api/admin/utilisateurs/'.$userId);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->em->getRepository(User::class)->find($userId));
    }

    public function testCannotDeleteOwnAccount(): void
    {
        $admin = $this->createAdmin();
        $this->loginAs($admin);

        $this->client->request('DELETE', '/api/admin/utilisateurs/'.$admin->getId());

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNotNull($this->em->getRepository(User::class)->find($admin->getId()));
    }
}
