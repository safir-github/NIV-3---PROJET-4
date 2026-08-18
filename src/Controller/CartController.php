<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du Panier (CartController) - Version temporaire (Étape B)
 * 
 * Ce contrôleur gère actuellement de manière temporaire l'action d'ajout au panier
 * pour valider la soumission du formulaire de la fiche produit.
 */
class CartController extends AbstractController
{
    #[Route('/cart/add/{id}', name: 'app_cart_add', methods: ['POST'])]
    public function add(int $id, Request $request): Response
    {
        // Récupération de la taille sélectionnée dans le formulaire POST
        $size = $request->request->get('size');

        // Validation défensive : on vérifie que la taille a bien été transmise
        if (!$size) {
            $this->addFlash('error', 'Veuillez sélectionner une taille.');
            return $this->redirectToRoute('app_product_show', ['id' => $id]);
        }

        // Affichage d'un message de confirmation temporaire en attendant le vrai service panier
        return new Response(sprintf(
            '<html><body><h1>Ajout au panier réussi !</h1><p>Produit ID : %d avec la taille : %s a été soumis.</p><a href="/products">Retourner à la boutique</a></body></html>',
            $id,
            htmlspecialchars($size)
        ));
    }
}
