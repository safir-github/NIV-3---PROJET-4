<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du Panier (CartController)
 * 
 * Ce contrôleur gère :
 * - L'affichage du panier d'achat (`/cart`).
 * - L'ajout d'un produit avec sa taille (`/cart/add/{id}`).
 * - La suppression d'un produit spécifique (`/cart/remove/{id}/{size}`).
 */
class CartController extends AbstractController
{
    private CartService $cartService;
    private ProductRepository $productRepository;

    public function __construct(CartService $cartService, ProductRepository $productRepository)
    {
        $this->cartService = $cartService;
        $this->productRepository = $productRepository;
    }

    #[Route('/cart', name: 'app_cart')]
    public function index(): Response
    {
        // Récupération des informations détaillées du panier
        $detailedCart = $this->cartService->getDetailedCart();
        $total = $this->cartService->getTotal();

        return $this->render('cart/index.html.twig', [
            'cart_items' => $detailedCart,
            'total' => $total
        ]);
    }

    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        // Récupération de la taille soumise dans le formulaire
        $size = $request->request->get('size');

        // Style défensif : on vérifie si le produit existe bien en BDD
        $product = $this->productRepository->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Le produit que vous essayez d\'ajouter n\'existe pas.');
        }

        // Style défensif : validation de la sélection de la taille
        if (!$size) {
            $this->addFlash('error', 'Veuillez sélectionner une taille.');
            return $this->redirectToRoute('app_product_show', ['id' => $id]);
        }

        // Style défensif : on vérifie que la taille demandée dispose bien d'un stock > 0
        $hasStock = false;
        foreach ($product->getStocks() as $stock) {
            if ($stock->getSize() === $size && $stock->getQuantity() > 0) {
                $hasStock = true;
                break;
            }
        }

        if (!$hasStock) {
            $this->addFlash('error', sprintf('La taille %s pour ce produit est actuellement en rupture de stock.', $size));
            return $this->redirectToRoute('app_product_show', ['id' => $id]);
        }

        // Ajout au panier via le service
        $this->cartService->add($id, $size);

        $this->addFlash('success', sprintf('Le sweat-shirt %s (taille %s) a été ajouté à votre panier.', $product->getName(), $size));

        // Redirection classique vers la page du panier (gérée proprement par Turbo)
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{id}/{size}', name: 'app_cart_remove')]
    public function remove(int $id, string $size): Response
    {
        // Suppression de la ligne produit + taille via le service
        $this->cartService->remove($id, $size);

        $this->addFlash('success', 'L\'article a été retiré de votre panier.');

        // Redirection classique vers le panier
        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/checkout', name: 'app_cart_checkout')]
    public function checkoutPlaceholder(): Response
    {
        // Page temporaire en attendant l'intégration de Stripe
        return new Response('<html><body><h1>Paiement & Intégration Stripe (Phase 6)</h1><p>Cette page est en cours de développement. Le module de paiement test par Stripe sera implémenté à la prochaine étape !</p><a href="/cart">Retourner au panier</a></body></html>');
    }
}
