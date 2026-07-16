<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductVariant;
use App\Entity\PromoCode;
use App\Entity\ShippingMethod;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $em): void
    {
        $this->faker->seed(42); // reproductible

        $this->loadSettings($em);
        $shippingMethods  = $this->loadShippingMethods($em);
        $productCategories = $this->loadProductCategories($em);
        $products         = $this->loadProducts($em, $productCategories);
        $this->loadPromoCodes($em);
        $this->loadUsers($em);
        $customers = $this->loadCustomers($em);
        $this->loadOrders($em, $customers, $products, $shippingMethods);

        $em->flush();
    }

    // ── Settings ────────────────────────────────────────────────────────────────

    private function loadSettings(ObjectManager $em): void
    {
        $defaults = [
            'site.maintenance'         => 'false',
            'shop.enabled'             => 'true',
            'invoice.trigger'          => 'on_confirm',
            'invoice.default_tax_rate' => '20',
        ];

        foreach ($defaults as $key => $value) {
            $setting = new AppSetting();
            $setting->setSettingKey($key)->setValue($value);
            $em->persist($setting);
        }
    }

    // ── Méthodes de livraison ────────────────────────────────────────────────────

    /** @return ShippingMethod[] */
    private function loadShippingMethods(ObjectManager $em): array
    {
        $methods = [
            [
                'name'            => 'Colissimo',
                'description'     => 'Livraison à domicile en 2-3 jours ouvrés.',
                'price'           => 490,
                'freeAboveAmount' => 5000,
                'position'        => 1,
            ],
            [
                'name'            => 'Point relais',
                'description'     => 'Retrait en point relais en 3-5 jours ouvrés.',
                'price'           => 290,
                'freeAboveAmount' => 3500,
                'position'        => 2,
            ],
            [
                'name'            => 'Chronopost Express',
                'description'     => 'Livraison express le lendemain avant 13h.',
                'price'           => 1290,
                'freeAboveAmount' => null,
                'position'        => 3,
            ],
        ];

        $entities = [];
        foreach ($methods as $data) {
            $m = new ShippingMethod();
            $m->setName($data['name'])
              ->setDescription($data['description'])
              ->setPrice($data['price'])
              ->setFreeAboveAmount($data['freeAboveAmount'])
              ->setPosition($data['position'])
              ->setIsActive(true);
            $em->persist($m);
            $entities[] = $m;
        }

        return $entities;
    }

    // ── Catégories de produits ───────────────────────────────────────────────────

    /** @return ProductCategory[] */
    private function loadProductCategories(ObjectManager $em): array
    {
        $categories = [
            ['name' => 'Vêtements',   'slug' => 'vetements',   'taxRate' => 20,  'position' => 1],
            ['name' => 'Chaussures',  'slug' => 'chaussures',  'taxRate' => 20,  'position' => 2],
            ['name' => 'Accessoires', 'slug' => 'accessoires', 'taxRate' => 20,  'position' => 3],
            ['name' => 'Alimentation','slug' => 'alimentation','taxRate' => 5,   'position' => 4],
            ['name' => 'Livres',      'slug' => 'livres',      'taxRate' => 5,   'position' => 5],
        ];

        $entities = [];
        foreach ($categories as $data) {
            $cat = new ProductCategory();
            $cat->setName($data['name'])
                ->setSlug($data['slug'])
                ->setTaxRate($data['taxRate'])
                ->setPosition($data['position'])
                ->setIsActive(true);
            $em->persist($cat);
            $entities[$data['slug']] = $cat;
        }

        return $entities;
    }

    // ── Produits ─────────────────────────────────────────────────────────────────

    /**
     * @param  ProductCategory[] $categories
     * @return Product[]
     */
    private function loadProducts(ObjectManager $em, array $categories): array
    {
        $products = [];

        // Produits simples (sans variantes)
        $simpleProducts = [
            ['name' => 'T-shirt Essentiel',       'slug' => 't-shirt-essentiel',       'price' => 1990,  'cat' => 'vetements',    'stock' => 50],
            ['name' => 'Jean Slim Coupe Moderne',  'slug' => 'jean-slim-coupe-moderne',  'price' => 5990,  'cat' => 'vetements',    'stock' => 30],
            ['name' => 'Hoodie Premium',           'slug' => 'hoodie-premium',           'price' => 4990,  'cat' => 'vetements',    'stock' => 25, 'compareAt' => 6990],
            ['name' => 'Robe Fleurie Été',         'slug' => 'robe-fleurie-ete',         'price' => 3490,  'cat' => 'vetements',    'stock' => 20],
            ['name' => 'Sneakers Urban',           'slug' => 'sneakers-urban',           'price' => 8990,  'cat' => 'chaussures',   'stock' => 40, 'compareAt' => 11990],
            ['name' => 'Bottines Cuir',            'slug' => 'bottines-cuir',            'price' => 12990, 'cat' => 'chaussures',   'stock' => 15],
            ['name' => 'Sandales Confort',         'slug' => 'sandales-confort',         'price' => 4490,  'cat' => 'chaussures',   'stock' => 35],
            ['name' => 'Ceinture Cuir Tressée',    'slug' => 'ceinture-cuir-tressee',    'price' => 2990,  'cat' => 'accessoires',  'stock' => 60],
            ['name' => 'Sac à Dos Urbain',         'slug' => 'sac-a-dos-urbain',         'price' => 6990,  'cat' => 'accessoires',  'stock' => 20],
            ['name' => 'Portefeuille Minimaliste', 'slug' => 'portefeuille-minimaliste', 'price' => 3490,  'cat' => 'accessoires',  'stock' => 45],
            ['name' => 'Café Bio en Grains 500g',  'slug' => 'cafe-bio-en-grains-500g',  'price' => 1290,  'cat' => 'alimentation', 'stock' => 100],
            ['name' => 'Miel Artisanal 250g',      'slug' => 'miel-artisanal-250g',      'price' => 890,   'cat' => 'alimentation', 'stock' => 80],
            ['name' => 'Tisane Relaxante Bio',     'slug' => 'tisane-relaxante-bio',     'price' => 590,   'cat' => 'alimentation', 'stock' => 120],
            ['name' => 'Symfony 7 en pratique',    'slug' => 'symfony-7-en-pratique',    'price' => 3500,  'cat' => 'livres',       'stock' => 30],
            ['name' => 'Clean Code PHP',           'slug' => 'clean-code-php',           'price' => 2990,  'cat' => 'livres',       'stock' => 25, 'compareAt' => 3990],
        ];

        foreach ($simpleProducts as $data) {
            $product = $this->makeProduct(
                $data['name'],
                $data['slug'],
                $data['price'],
                $data['compareAt'] ?? null,
                $data['stock'],
                false,
                $categories[$data['cat']],
                $em,
            );
            $em->persist($product);
            $products[] = $product;
        }

        // Produits avec variantes (vêtements taillés)
        $variantProducts = [
            [
                'name'    => 'Polo Classic',
                'slug'    => 'polo-classic',
                'price'   => 3490,
                'cat'     => 'vetements',
                'options' => [
                    ['name' => 'Blanc / S',   'sku' => 'POLO-W-S',  'price' => null, 'stock' => 10],
                    ['name' => 'Blanc / M',   'sku' => 'POLO-W-M',  'price' => null, 'stock' => 15],
                    ['name' => 'Blanc / L',   'sku' => 'POLO-W-L',  'price' => null, 'stock' => 12],
                    ['name' => 'Blanc / XL',  'sku' => 'POLO-W-XL', 'price' => null, 'stock' => 8],
                    ['name' => 'Marine / S',  'sku' => 'POLO-N-S',  'price' => null, 'stock' => 10],
                    ['name' => 'Marine / M',  'sku' => 'POLO-N-M',  'price' => null, 'stock' => 14],
                    ['name' => 'Marine / L',  'sku' => 'POLO-N-L',  'price' => null, 'stock' => 10],
                    ['name' => 'Marine / XL', 'sku' => 'POLO-N-XL', 'price' => null, 'stock' => 5],
                ],
            ],
            [
                'name'    => 'Veste Légère Printemps',
                'slug'    => 'veste-legere-printemps',
                'price'   => 7990,
                'cat'     => 'vetements',
                'options' => [
                    ['name' => 'Kaki / S',   'sku' => 'VLP-K-S',  'price' => null, 'stock' => 6],
                    ['name' => 'Kaki / M',   'sku' => 'VLP-K-M',  'price' => null, 'stock' => 8],
                    ['name' => 'Kaki / L',   'sku' => 'VLP-K-L',  'price' => null, 'stock' => 7],
                    ['name' => 'Beige / M',  'sku' => 'VLP-B-M',  'price' => null, 'stock' => 5],
                    ['name' => 'Beige / L',  'sku' => 'VLP-B-L',  'price' => null, 'stock' => 4],
                ],
            ],
            [
                'name'    => 'Oxford Premium',
                'slug'    => 'oxford-premium',
                'price'   => 9990,
                'cat'     => 'chaussures',
                'options' => [
                    ['name' => 'Noir / 40', 'sku' => 'OXF-N-40', 'price' => null, 'stock' => 5],
                    ['name' => 'Noir / 41', 'sku' => 'OXF-N-41', 'price' => null, 'stock' => 7],
                    ['name' => 'Noir / 42', 'sku' => 'OXF-N-42', 'price' => null, 'stock' => 8],
                    ['name' => 'Noir / 43', 'sku' => 'OXF-N-43', 'price' => null, 'stock' => 6],
                    ['name' => 'Noir / 44', 'sku' => 'OXF-N-44', 'price' => null, 'stock' => 4],
                    ['name' => 'Marron / 41', 'sku' => 'OXF-M-41', 'price' => 10990, 'stock' => 5],
                    ['name' => 'Marron / 42', 'sku' => 'OXF-M-42', 'price' => 10990, 'stock' => 6],
                    ['name' => 'Marron / 43', 'sku' => 'OXF-M-43', 'price' => 10990, 'stock' => 4],
                ],
            ],
            [
                'name'    => 'Montre Minimaliste',
                'slug'    => 'montre-minimaliste',
                'price'   => 14990,
                'cat'     => 'accessoires',
                'options' => [
                    ['name' => 'Cadran Blanc / Bracelet Noir',  'sku' => 'MON-WB', 'price' => null,  'stock' => 8],
                    ['name' => 'Cadran Blanc / Bracelet Brun',  'sku' => 'MON-WG', 'price' => null,  'stock' => 6],
                    ['name' => 'Cadran Noir / Bracelet Noir',   'sku' => 'MON-BB', 'price' => 15990, 'stock' => 10],
                    ['name' => 'Cadran Noir / Bracelet Brun',   'sku' => 'MON-BG', 'price' => 15990, 'stock' => 7],
                ],
            ],
        ];

        foreach ($variantProducts as $data) {
            $product = $this->makeProduct(
                $data['name'],
                $data['slug'],
                $data['price'],
                null,
                0,
                true,
                $categories[$data['cat']],
                $em,
            );

            foreach ($data['options'] as $i => $opt) {
                $variant = new ProductVariant();
                $variant->setProduct($product)
                        ->setName($opt['name'])
                        ->setSku($opt['sku'])
                        ->setPriceOverride($opt['price'])
                        ->setStock($opt['stock'])
                        ->setIsActive(true)
                        ->setPosition($i);
                $em->persist($variant);
            }

            $em->persist($product);
            $products[] = $product;
        }

        return $products;
    }

    private function makeProduct(
        string $name,
        string $slug,
        int $price,
        ?int $compareAtPrice,
        int $stock,
        bool $hasVariants,
        ProductCategory $category,
        ObjectManager $em,
    ): Product {
        $product = new Product();
        $product->setName($name)
                ->setSlug($slug)
                ->setPrice($price)
                ->setCompareAtPrice($compareAtPrice)
                ->setStock($stock)
                ->setHasVariants($hasVariants)
                ->setStatus(Product::STATUS_PUBLISHED)
                ->setDescription($this->fakeEditorJsDescription($name));

        $product->syncCategories([$category]);

        return $product;
    }

    // ── Codes promo ──────────────────────────────────────────────────────────────

    private function loadPromoCodes(ObjectManager $em): void
    {
        $codes = [
            [
                'code'    => 'WELCOME10',
                'type'    => PromoCode::TYPE_PERCENT,
                'value'   => 10,
                'maxUses' => null,
                'expires' => null,
            ],
            [
                'code'    => 'SOLDES20',
                'type'    => PromoCode::TYPE_PERCENT,
                'value'   => 20,
                'maxUses' => 100,
                'expires' => new \DateTimeImmutable('+30 days'),
            ],
            [
                'code'    => 'FRAIS5',
                'type'    => PromoCode::TYPE_FIXED,
                'value'   => 500,
                'maxUses' => 50,
                'expires' => null,
            ],
        ];

        foreach ($codes as $data) {
            $promo = new PromoCode();
            $promo->setCode($data['code'])
                  ->setType($data['type'])
                  ->setValue($data['value'])
                  ->setMaxUses($data['maxUses'])
                  ->setExpiresAt($data['expires'])
                  ->setIsActive(true);
            $em->persist($promo);
        }
    }

    // ── Utilisateurs admin ───────────────────────────────────────────────────────

    private function loadUsers(ObjectManager $em): void
    {
        $admin = new User();
        $admin->setEmail('florimond.jouffroy@gmail.com')
              ->setRoles(['ROLE_ADMIN'])
              ->setIsVerified(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, '7@changer'));
        $em->persist($admin);

        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user{$i}@example.com")
                 ->setRoles([])
                 ->setIsVerified(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $em->persist($user);
        }
    }

    // ── Clients ──────────────────────────────────────────────────────────────────

    /** @return Customer[] */
    private function loadCustomers(ObjectManager $em): array
    {
        $customers = [];

        for ($i = 0; $i < 12; $i++) {
            $customer = new Customer();
            $customer->setFirstName($this->faker->firstName())
                     ->setLastName($this->faker->lastName())
                     ->setEmail($this->faker->unique()->safeEmail())
                     ->setPhone($this->faker->optional(0.6)->phoneNumber());
            $em->persist($customer);
            $customers[] = $customer;
        }

        return $customers;
    }

    // ── Commandes ────────────────────────────────────────────────────────────────

    /**
     * @param Customer[]      $customers
     * @param Product[]       $products
     * @param ShippingMethod[] $shippingMethods
     */
    private function loadOrders(
        ObjectManager $em,
        array $customers,
        array $products,
        array $shippingMethods,
    ): void {
        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_DELIVERED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
        ];

        $seq = 1;
        for ($i = 0; $i < 20; $i++) {
            $customer = $customers[array_rand($customers)];
            $shipping = $shippingMethods[array_rand($shippingMethods)];
            $status   = $statuses[array_rand($statuses)];

            $order = new Order();
            $order->setOrderNumber(sprintf('ORD-%s-%05d', date('Ymd'), $seq++))
                  ->setCustomer($customer)
                  ->setStatus($status)
                  ->setShippingAddress([
                      'firstName'  => $customer->getFirstName(),
                      'lastName'   => $customer->getLastName(),
                      'line1'      => $this->faker->streetAddress(),
                      'city'       => $this->faker->city(),
                      'postalCode' => $this->faker->postcode(),
                      'country'    => 'France',
                  ]);

            // 2 à 4 lignes par commande
            $subtotal = 0;
            $itemCount = $this->faker->numberBetween(2, 4);
            $pickedProducts = $this->faker->randomElements($products, $itemCount);

            foreach ($pickedProducts as $product) {
                $qty   = $this->faker->numberBetween(1, 3);
                $price = $product->getPrice();

                $item = new OrderItem();
                $item->setOrder($order)
                     ->setProduct($product)
                     ->setProductName($product->getName())
                     ->setUnitPrice($price)
                     ->setQuantity($qty);
                $item->recalculateTotal();

                $em->persist($item);
                $subtotal += $item->getTotal();
            }

            $shippingCost = $shipping->getEffectivePrice($subtotal);

            $order->setSubtotal($subtotal)
                  ->setDiscountAmount(0)
                  ->setShippingAmount($shippingCost)
                  ->setTotal($subtotal + $shippingCost);

            $em->persist($order);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /** Génère un contenu EditorJS minimal compatible avec le renderer du front. */
    private function fakeEditorJsDescription(string $productName): array
    {
        return [
            'time'    => time() * 1000,
            'version' => '2.26.5',
            'blocks'  => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => $this->faker->sentence(12)],
                ],
                [
                    'type' => 'paragraph',
                    'data' => ['text' => $this->faker->sentence(10)],
                ],
            ],
        ];
    }
}
