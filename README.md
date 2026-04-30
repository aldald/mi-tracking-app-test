# SMI Tracking App

Application Shopify permettant de connecter une boutique Shopify à un backend externe pour le tracking des ventes et la gestion des codes promos.

## Prérequis

- PHP 8.1+
- MySQL
- Apache (Laragon recommandé)
- ngrok (pour exposer le serveur local)
- Un compte Shopify Partners avec une dev store

## Installation

1. Clone le repository :
git clone https://github.com/ton-username/smi-tracking-app.git

2. Copie le fichier `.env.example` en `.env` et remplis les valeurs :
cp .env.example .env

3. Importe le schéma de la base de données :
mysql -u root -p < db/schema.sql

4. Lance ngrok :
ngrok http 80

5. Mets à jour les URLs dans `.env` avec ton URL ngrok :
APP_URL=https://xxx.ngrok-free.app/smi-tracking-app
SHOPIFY_REDIRECT_URI=https://xxx.ngrok-free.app/smi-tracking-app/auth/callback

6. Mets à jour les URLs dans le Shopify Dev Dashboard

7. Enregistre le webhook :
http://localhost/smi-tracking-app/register_webhook.php

## Configuration (variables d'environnement)

| Variable | Description |
|---|---|
| SHOPIFY_API_KEY | Client ID de l'app Shopify |
| SHOPIFY_API_SECRET | Client Secret de l'app Shopify |
| SHOPIFY_WEBHOOK_SECRET | Secret pour la vérification HMAC |
| SHOPIFY_SCOPES | Scopes OAuth |
| SHOPIFY_REDIRECT_URI | URL de callback OAuth |
| APP_URL | URL publique de l'application |
| DB_HOST | Hôte MySQL |
| DB_PORT | Port MySQL |
| DB_NAME | Nom de la base de données |
| DB_USER | Utilisateur MySQL |
| DB_PASS | Mot de passe MySQL |
| EXTERNAL_API_URL | URL de l'API externe |

## Scopes utilisés

- `read_orders` — Lecture des commandes
- `read_discounts` — Lecture des codes promos
- `write_discounts` — Création des codes promos
- `write_price_rules` — Création des price rules Shopify

## Endpoints disponibles

| Méthode | Endpoint | Description |
|---|---|---|
| GET | `/auth/install?shop=xxx.myshopify.com` | Installation OAuth |
| GET | `/auth/callback` | Callback OAuth |
| POST | `/webhooks/orders` | Réception des webhooks commandes |
| POST | `/orders/status` | Mise à jour du statut d'une commande |
| POST | `/discount-codes` | Création d'un code promo dans Shopify |

## Exemples d'utilisation

### Mise à jour du statut d'une commande
POST /orders/status
{
    "order_id": "123456",
    "status": "accepted"
}

### Création d'un code promo
POST /discount-codes
{
    "code": "USER10",
    "type": "percentage",
    "value": 10,
    "starts_at": "2026-05-01T00:00:00Z"
}