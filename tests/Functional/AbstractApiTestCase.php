<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Customer;
use App\Entity\FaqItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\PasswordResetToken;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductReview;
use App\Entity\StaticPage;
use App\Entity\SupportTicket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AbstractApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function postJson(string $url, array $payload): void
    {
        $this->client->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );
    }

    protected function getJson(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(
        string $email = 'user@example.com',
        string $password = 'password123',
        bool $verified = true,
        array $roles = [],
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setRoles($roles);

        if ($verified) {
            $user->setIsVerified(true);
        } else {
            $user->setVerificationToken(bin2hex(random_bytes(32)));
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function createAdmin(string $email = 'admin@example.com'): User
    {
        return $this->createUser($email, roles: ['ROLE_ADMIN']);
    }

    protected function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    protected function putJson(string $url, array $payload): void
    {
        $this->client->request(
            'PUT',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );
    }

    protected function patchJson(string $url, array $payload): void
    {
        $this->client->request(
            'PATCH',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload),
        );
    }

    protected function createCustomer(
        string $email = 'client@example.com',
        string $firstName = 'Jean',
        string $lastName = 'Dupont',
    ): Customer {
        $customer = new Customer();
        $customer->setEmail($email);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        $this->em->persist($customer);
        $this->em->flush();

        return $customer;
    }

    protected function createProductCategory(
        string $name = 'Vêtements',
        string $slug = 'vetements',
    ): ProductCategory {
        $category = new ProductCategory();
        $category->setName($name);
        $category->setSlug($slug);

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    protected function createProduct(
        string $name = 'T-shirt blanc',
        string $slug = 't-shirt-blanc',
        string $status = Product::STATUS_DRAFT,
    ): Product {
        $product = new Product();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setStatus($status);
        $product->setPrice(1999);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    protected function createOrder(
        Customer $customer,
        string $orderNumber = 'ORD-20240101-00001',
        string $status = Order::STATUS_PENDING,
    ): Order {
        $order = new Order();
        $order->setOrderNumber($orderNumber);
        $order->setCustomer($customer);
        $order->setStatus($status);
        $order->setShippingAddress([
            'firstName'  => $customer->getFirstName(),
            'lastName'   => $customer->getLastName(),
            'line1'      => '1 rue de la Paix',
            'city'       => 'Paris',
            'postalCode' => '75001',
            'country'    => 'FR',
        ]);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }

    protected function createFaqItem(
        string $question = 'Quelle est votre politique de retour ?',
        string $answer = 'Vous disposez de 30 jours.',
        int $position = 1,
        bool $isActive = true,
    ): FaqItem {
        $item = new FaqItem();
        $item->setQuestion($question);
        $item->setAnswer($answer);
        $item->setPosition($position);
        $item->setIsActive($isActive);

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    protected function createStaticPage(
        string $title = 'Mentions légales',
        string $slug = 'mentions-legales',
        bool $isActive = true,
        array $content = [],
    ): StaticPage {
        $page = new StaticPage();
        $page->setTitle($title);
        $page->setSlug($slug);
        $page->setContent($content);
        $page->setIsActive($isActive);

        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    protected function createSupportTicket(
        string $subject = 'Problème de commande',
        ?User $user = null,
        string $status = SupportTicket::STATUS_OPEN,
        ?string $guestName = null,
        ?string $guestEmail = null,
    ): SupportTicket {
        $ticket = new SupportTicket();
        $ticket->setSubject($subject);
        $ticket->setStatus($status);
        $ticket->setUser($user);
        $ticket->setGuestName($guestName);
        $ticket->setGuestEmail($guestEmail ?? ($user === null ? 'guest@example.com' : null));

        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    protected function createProductReview(
        Product $product,
        User $user,
        int $rating = 5,
        bool $isApproved = false,
        ?string $comment = null,
    ): ProductReview {
        $review = new ProductReview();
        $review->setProduct($product);
        $review->setUser($user);
        $review->setAuthorName($user->getEmail());
        $review->setRating($rating);
        $review->setComment($comment);
        $review->setIsApproved($isApproved);

        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }

    protected function createOrderWithProduct(
        Customer $customer,
        Product $product,
        string $orderNumber = 'ORD-20240101-00001',
        string $status = Order::STATUS_DELIVERED,
    ): Order {
        $order = $this->createOrder($customer, $orderNumber, $status);

        $item = new OrderItem();
        $item->setOrder($order);
        $item->setProduct($product);
        $item->setProductName($product->getName());
        $item->setUnitPrice($product->getPrice());
        $item->setQuantity(1);
        $item->recalculateTotal();

        $this->em->persist($item);
        $this->em->flush();

        return $order;
    }

    protected function createPasswordResetToken(User $user, string $code = '123456', int $ttlMinutes = 15): PasswordResetToken
    {
        $token = new PasswordResetToken($user, $code, new \DateTimeImmutable("+{$ttlMinutes} minutes"));

        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    protected function refreshUser(User $user): User
    {
        $this->em->refresh($user);

        return $user;
    }
}
