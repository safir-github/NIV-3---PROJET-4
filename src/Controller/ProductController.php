<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur des Produits (ProductController)
 * 
 * Ce contrôleur gère :
 * - L'affichage du catalogue de tous les sweat-shirts (`/products`).
 * - Le filtrage des produits par tranches de prix côté serveur.
 * - La route placeholder pour la fiche d'un produit spécifique (`/product/{id}`).
 */
class ProductController extends AbstractController
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    #[Route('/products', name: 'app_products')]
    public function index(Request $request): Response
    {
        // Récupération de la tranche de prix sélectionnée depuis les paramètres de l'URL (?price_filter=...)
        $priceFilter = $request->query->get('price_filter');

        // Initialisation de la variable qui contiendra nos produits filtrés ou complets
        $products = [];

        // Traitement du filtre côté serveur
        if ($priceFilter === '10-29') {
            // Tranche de 10 € à 29 € (excluant 29 € si on veut être strict, ou inclusif selon la méthode)
            $products = $this->productRepository->findByPriceRange(10.0, 29.0);
        } elseif ($priceFilter === '29-35') {
            // Tranche de 29 € à 35 €
            $products = $this->productRepository->findByPriceRange(29.0, 35.0);
        } elseif ($priceFilter === '35-50') {
            // Tranche de 35 € à 50 € (strictement selon le brief, BornInUsa à 59,90 € sera donc exclu)
            $products = $this->productRepository->findByPriceRange(35.0, 50.0);
        } else {
            // Par défaut ou si aucun filtre n'est appliqué, on affiche tous les sweat-shirts
            $products = $this->productRepository->findAll();
        }

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'current_filter' => $priceFilter, // Pour garder le bouton actif visuellement dans Twig
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_show')]
    public function show(int $id): Response
    {
        // Recherche du produit en base de données par son ID
        $product = $this->productRepository->find($id);

        // Style défensif : si le produit n'existe pas en BDD, on renvoie immédiatement une erreur 404
        if (!$product) {
            throw $this->createNotFoundException('Le sweat-shirt demandé n\'existe pas.');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
