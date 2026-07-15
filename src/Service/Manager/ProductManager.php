<?php

declare(strict_types=1);

namespace App\Service\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Florimond\LogBundle\Service\Manager\ApplicationLogManager;

/**
 * Gère les opérations métier sur les produits.
 *
 * Toutes les méthodes retournent bool ou ?Product pour indiquer le succès.
 * En cas d'exception lors du flush(), l'erreur est loguée et la méthode
 * retourne false/null pour permettre au contrôleur de renvoyer une 500 propre.
 */
class ProductManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationLogManager $logManager,
        private readonly ProductRepository $repository,
    ) {
    }

    /**
     * @param array<mixed> $categories ProductCategory[]
     */
    public function create(string $name, array $categories = []): ?Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setSlug($this->generateUniqueSlug($name));
        $product->syncCategories($categories);

        return $this->insert($product) ? $product : null;
    }

    /**
     * @param array<mixed>      $categories ProductCategory[]
     * @param array<mixed>|null $description Editor.js blocks
     * @param list<array{url: string, alt?: string|null, position?: int}> $images
     * @param list<array{id?: int|null, name: string, sku?: string|null, priceOverride?: int|null, stock: int, lowStockThreshold?: int, position?: int, attributes?: array<mixed>|null, isActive?: bool}> $variants
     */
    public function update(
        Product $product,
        string $name,
        ?array $description,
        int $price,
        ?int $compareAtPrice,
        int $stock,
        int $lowStockThreshold,
        bool $hasVariants,
        array $categories = [],
        array $images = [],
        array $variants = [],
    ): bool {
        if ($this->slugify($name) !== $this->slugify($product->getName())) {
            $product->setSlug($this->generateUniqueSlug($name, $product->getId()));
        }

        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice($price);
        $product->setCompareAtPrice($compareAtPrice);
        $product->setStock($stock);
        $product->setLowStockThreshold($lowStockThreshold);
        $product->setHasVariants($hasVariants);
        $product->syncCategories($categories);

        $this->syncImages($product, $images);
        $this->syncVariants($product, $variants);

        return $this->flush();
    }

    public function publish(Product $product): bool
    {
        $product->setStatus(Product::STATUS_PUBLISHED);

        return $this->flush();
    }

    public function unpublish(Product $product): bool
    {
        $product->setStatus(Product::STATUS_DRAFT);

        return $this->flush();
    }

    public function delete(Product $product): bool
    {
        $this->em->remove($product);

        return $this->flush();
    }

    public function insert(Product $product, bool $flush = true): bool
    {
        $this->em->persist($product);

        return $flush ? $this->flush() : true;
    }

    /**
     * Remplace toutes les images du produit.
     * Stratégie "delete all + re-insert" : plus simple qu'un diff,
     * suffisant car les images sont légères et peu nombreuses.
     *
     * @param list<array{url: string, alt?: string|null, position?: int}> $images
     */
    private function syncImages(Product $product, array $images): void
    {
        foreach ($product->getImages() as $img) {
            $this->em->remove($img);
        }

        foreach ($images as $i => $data) {
            $image = new ProductImage();
            $image->setProduct($product);
            $image->setUrl($data['url']);
            $image->setAlt($data['alt'] ?? null);
            $image->setPosition($data['position'] ?? $i);
            $this->em->persist($image);
        }
    }

    /**
     * Synchronise les variantes : met à jour les existantes (par id), supprime celles
     * absentes du tableau entrant, et crée les nouvelles (id absent ou inconnu).
     * On construit d'abord un index des variantes existantes par id pour éviter
     * des requêtes SELECT en boucle.
     *
     * @param list<array{id?: int|null, name: string, sku?: string|null, priceOverride?: int|null, stock: int, lowStockThreshold?: int, position?: int, attributes?: array<mixed>|null, isActive?: bool}> $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $existing = [];
        foreach ($product->getVariants() as $v) {
            $existing[$v->getId()] = $v;
        }

        $seen = [];
        foreach ($variants as $i => $data) {
            $id = isset($data['id']) ? (int) $data['id'] : null;
            if ($id && isset($existing[$id])) {
                $variant = $existing[$id];
            } else {
                $variant = new ProductVariant();
                $variant->setProduct($product);
                $this->em->persist($variant);
            }

            $variant->setName($data['name']);
            $variant->setSku(isset($data['sku']) && '' !== $data['sku'] ? $data['sku'] : null);
            $variant->setPriceOverride(isset($data['priceOverride']) ? (int) $data['priceOverride'] : null);
            $variant->setStock((int) ($data['stock'] ?? 0));
            $variant->setLowStockThreshold((int) ($data['lowStockThreshold'] ?? 5));
            $variant->setPosition($data['position'] ?? $i);
            $variant->setAttributes(isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : null);
            $variant->setIsActive((bool) ($data['isActive'] ?? true));

            if ($id) {
                $seen[$id] = true;
            }
        }

        foreach ($existing as $id => $variant) {
            if (!isset($seen[$id])) {
                $this->em->remove($variant);
            }
        }
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logManager->reportException($e);

            return false;
        }

        return true;
    }

    /**
     * Génère un slug unique en base. Si "t-shirt" existe déjà, tente "t-shirt-2",
     * "t-shirt-3", etc. excludeId permet d'exclure le produit lui-même lors d'un
     * renommage (sinon il se trouverait lui-même et incrémenterait indéfiniment).
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base    = $this->slugify($name);
        $slug    = $base;
        $counter = 2;

        while (true) {
            $existing = $this->repository->findBySlug($slug);
            if (null === $existing || $existing->getId() === $excludeId) {
                break;
            }
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * Convertit un texte en slug ASCII minuscule (ex. "T-Shirt Blanc" → "t-shirt-blanc").
     * iconv TRANSLIT convertit les accents (é → e, ñ → n, etc.).
     * IGNORE supprime les caractères qui ne peuvent pas être translittérés.
     */
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = (string) preg_replace('/[^a-z0-9\-]/', '-', $text);
        $text = (string) preg_replace('/-+/', '-', $text);

        return trim($text, '-') ?: 'produit';
    }
}
