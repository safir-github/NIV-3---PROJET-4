<?php

namespace App\Entity;

// Importation des classes nécessaires pour Doctrine (ORM) et la Sécurité de Symfony
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Entité User (Utilisateur)
 * 
 * Cette classe représente la table 'user' dans notre base de données.
 * Elle implémente :
 * - UserInterface : nécessaire pour que Symfony gère cet utilisateur dans son système de sécurité.
 * - PasswordAuthenticatedUserInterface : nécessaire pour la gestion et le hachage du mot de passe.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // L'identifiant unique généré automatiquement en base de données (Clé Primaire)
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // L'adresse email de l'utilisateur (doit être unique)
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    // Les rôles de l'utilisateur (ex: ROLE_USER, ROLE_ADMIN) sous forme de tableau JSON en BDD
    #[ORM\Column]
    private array $roles = [];

    /**
     * Le mot de passe haché de l'utilisateur
     */
    #[ORM\Column]
    private ?string $password = null;

    // Le nom complet de l'utilisateur (Client ou Administrateur)
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // L'adresse de livraison (facultative pour les admins, obligatoire pour les clients)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deliveryAddress = null;

    // Statut d'activation du compte (vérifié par email)
    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    // --- GETTERS ET SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Retourne l'identifiant visuel unique de l'utilisateur (ici, l'email).
     * Requis par UserInterface.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Retourne la liste des rôles de l'utilisateur.
     * Requis par UserInterface.
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // On s'assure que chaque utilisateur possède au moins le rôle de base 'ROLE_USER'
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Retourne le mot de passe haché.
     * Requis par PasswordAuthenticatedUserInterface.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // --- NOUVEAUX CHAMPS SPÉCIFIQUES À NOTRE BRIEF ---

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * Méthode de sérialisation pour stocker l'utilisateur en session de manière sécurisée.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        // On évite de stocker le hash réel du mot de passe en session
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
