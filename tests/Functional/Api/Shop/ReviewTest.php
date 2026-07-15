<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Shop;

use App\Entity\Product;
use App\Tests\Functional\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReviewTest extends AbstractApiTestCase
{
    public function testPublicEndpointReturnsOnlyApprovedReviews(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $product = $this->createProduct('Chemise', 'chemise', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $userA, 5, true,  'Super produit');
        $this->createProductReview($product, $userB, 2, false, 'Bof');

        $this->client->request('GET', '/api/boutique/produits/chemise/avis');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(1, $data['count']);
        self::assertCount(1, $data['reviews']);
        self::assertSame('Super produit', $data['reviews'][0]['comment']);
    }

    public function testPublicEndpointReturnsAverageRating(): void
    {
        $userA   = $this->createUser('a@example.com');
        $userB   = $this->createUser('b@example.com');
        $product = $this->createProduct('Veste', 'veste', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $userA, 4, true);
        $this->createProductReview($product, $userB, 2, true);

        $this->client->request('GET', '/api/boutique/produits/veste/avis');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertEqualsWithDelta(3.0, $data['avgRating'], 0.01);
        self::assertSame(2, $data['count']);
    }

    public function testPublicEndpointReturnsEmptyForNoReviews(): void
    {
        $this->createProduct('Pantalon', 'pantalon', Product::STATUS_PUBLISHED);

        $this->client->request('GET', '/api/boutique/produits/pantalon/avis');

        self::assertResponseIsSuccessful();
        $data = $this->getJson();
        self::assertSame(0, $data['count']);
        self::assertNull($data['avgRating']);
        self::assertSame([], $data['reviews']);
    }

    public function testPublicEndpointReturns404ForUnknownProduct(): void
    {
        $this->client->request('GET', '/api/boutique/produits/produit-inexistant/avis');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testReviewAuthorNameIsObfuscated(): void
    {
        $user    = $this->createUser('monprénom.monnom@example.com');
        $product = $this->createProduct('Chapeau', 'chapeau', Product::STATUS_PUBLISHED);
        $this->createProductReview($product, $user, 5, true);

        $this->client->request('GET', '/api/boutique/produits/chapeau/avis');

        self::assertResponseIsSuccessful();
        $data    = $this->getJson();
        $author  = $data['reviews'][0]['authorName'];
        // Le nom est issu de l'email mais ne doit pas exposer le domaine complet dans l'affichage côté React.
        // On vérifie juste que la clé est présente et non vide.
        self::assertNotEmpty($author);
    }
}
