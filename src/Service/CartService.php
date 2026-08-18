<?php

namespace App\Service;

use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service CartService (Gestion du Panier en Session)
 * 
 * Ce service gère toute la logique du panier d'achat stocké en session.
 * Il permet d'ajouter un produit, de le supprimer, de calculer le montant total 
 * et de récupérer le panier détaillé (avec les objets Product de la base de données).
 */
class CartService
{
    private RequestStack $requestStack;
    private ProductRepository $productRepository;

    public function __construct(RequestStack $requestStack, ProductRepository $productRepository)
    {
        $this->requestStack = $requestStack;
        $this->productRepository = $productRepository;
    }

    /**
     * Récupère la session courante.
     */
    private function getSession()
    {
        return $this->requestStack->getSession();
    }

    /**
     * Récupère le panier brut depuis la session.
     * Le panier est stocké sous la forme :
     * [
     *   "ID_PRODUIT-TAILLE" => quantity
     * ]
     */
    public function getRawCart(): array
    {
        return $this->getSession()->get('cart', []);
    }

    /**
     * Enregistre les modifications du panier en session.
     */
    private function saveCart(array $cart): void
    {
        $this->getSession()->set('cart', $cart);
    }

    /**
     * Ajoute un article (produit + taille) au panier.
     */
    public function add(int $id, string $size): void
    {
        $cart = $this->getRawCart();
        $key = $id . '-' . $size; // Clé unique combinant l'ID et la taille (ex: "12-M")

        if (!empty($cart[$key])) {
            $cart[$key]++; // Si déjà présent, on incrémente la quantité
        } else {
            $cart[$key] = 1; // Sinon, on l'initialise à 1
        }

        $this->saveCart($cart);
    }

    /**
     * Supprime un article (produit + taille) du panier.
     */
    public function remove(int $id, string $size): void
    {
        $cart = $this->getRawCart();
        $key = $id . '-' . $size;

        if (!empty($cart[$key])) {
            unset($cart[$key]); // Supprime la ligne complète
        }

        $this->saveCart($cart);
    }

    /**
     * Met à jour la quantité d'un article du panier.
     */
    public function updateQuantity(int $id, string $size, int $quantity): void
    {
        $cart = $this->getRawCart();
        $key = $id . '-' . $size;

        if (isset($cart[$key])) {
            if ($quantity <= 0) {
                unset($cart[$key]);
            } else {
                $cart[$key] = $quantity;
            }
            $this->saveCart($cart);
        }
    }

    /**
     * Récupère le panier détaillé avec les informations complètes des produits 
     * récupérées de la base de données (image, nom, prix, etc.).
     * 
     * @return array[] Un tableau contenant les détails de chaque article
     */
    public function getDetailedCart(): array
    {
        $rawCart = $this->getRawCart();
        $detailedCart = [];

        foreach ($rawCart as $key => $quantity) {
            // Extraction de l'ID et de la taille à partir de la clé unique
            list($id, $size) = explode('-', $key);
            $product = $this->productRepository->find((int)$id);

            // Style défensif : si le produit existe toujours en BDD, on l'ajoute au panier détaillé
            if ($product) {
                $subTotal = $product->getPrice() * $quantity;
                $detailedCart[] = [
                    'product' => $product,
                    'size' => $size,
                    'quantity' => $quantity,
                    'sub_total' => $subTotal
                ];
            } else {
                // Si le produit n'existe plus en BDD, on le nettoie du panier session
                $this->remove((int)$id, $size);
            }
        }

        return $detailedCart;
    }

    /**
     * Calcule le prix total de l'ensemble du panier.
     */
    public function getTotal(): float
    {
        $detailedCart = $this->getDetailedCart();
        $total = 0.0;

        foreach ($detailedCart as $item) {
            $total += $item['sub_total'];
        }

        return $total;
    }

    /**
     * Vide complètement le panier en session.
     */
    public function clear(): void
    {
        $this->getSession()->remove('cart');
    }
}
