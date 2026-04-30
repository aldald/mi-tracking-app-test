# SMI Tracking App

Application Shopify permettant de connecter une boutique Shopify à un backend externe pour le tracking des ventes et la gestion des codes promos.

Cette application gère :
- Le tracking des commandes via webhooks
- La synchronisation des statuts de commandes
- La création de codes promos dans Shopify

---

## Prérequis

- PHP 8.1+
- MySQL 5.7+
- Apache (Laragon recommandé sur Windows)
- ngrok (pour exposer le serveur local à Shopify)
- Un compte Shopify Partners
- Une boutique de développement Shopify

---

## Installation

### 1. Cloner le repository

```bash
git clone https://github.com/aldald/mi-tracking-app-test.git
cd mi-tracking-app-test
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
```

Remplis les valeurs dans `.env` (voir section Configuration ci-dessous).

### 3. Créer la base de données

Importe le schéma SQL via phpMyAdmin ou en ligne de commande :

```bash
mysql -u root -p < db/schema.sql
```

Cela va créer la base `smi_tracking` avec les tables :
- `shops` — stocke les boutiques connectées et leurs access tokens
- `orders` — stocke les commandes reçues via webhook
- `discount_codes` — stocke les codes promos créés

### 4. Exposer le serveur avec ngrok

Lance ngrok pour obtenir une URL publique :

```bash
ngrok http 80
```

Tu obtiendras une URL du type : `https://xxxx.ngrok-free.app`

### 5. Mettre à jour les URLs

Dans `.env`, mets à jour :

```env
APP_URL=https://xxxx.ngrok-free.app/smi-tracking-app
SHOPIFY_REDIRECT_URI=https://xxxx.ngrok-free.app/smi-tracking-app/auth/callback
```

Dans le **Shopify Dev Dashboard** → ton app → Versions → Création de version :
- URL de l'application : `https://xxxx.ngrok-free.app/smi-tracking-app`
- URL de redirection : `https://xxxx.ngrok-free.app/smi-tracking-app/auth/callback`

### 6. Installer l'application sur ta boutique

Ouvre dans le navigateur :

```
https://xxxx.ngrok-free.app/smi-tracking-app/auth/install?shop=TON-SHOP.myshopify.com
```

### 7. Enregistrer le webhook

Ouvre dans le navigateur :

```
http://localhost/smi-tracking-app/register_webhook.php
```

---

## Configuration (variables d'environnement)

| Variable | Description | Exemple |
|---|---|---|
| `SHOPIFY_API_KEY` | Client ID de l'app Shopify | `e8848e72b18f...` |
| `SHOPIFY_API_SECRET` | Client Secret de l'app Shopify | `shpss_c1d1da49...` |
| `SHOPIFY_WEBHOOK_SECRET` | Secret pour la vérification HMAC (même valeur que API_SECRET) | `shpss_c1d1da49...` |
| `SHOPIFY_SCOPES` | Scopes OAuth demandés | `read_orders,read_discounts,write_discounts,write_price_rules` |
| `SHOPIFY_REDIRECT_URI` | URL de callback OAuth | `https://xxxx.ngrok-free.app/smi-tracking-app/auth/callback` |
| `APP_URL` | URL publique de l'application | `https://xxxx.ngrok-free.app/smi-tracking-app` |
| `DB_HOST` | Hôte MySQL | `127.0.0.1` |
| `DB_PORT` | Port MySQL | `3306` |
| `DB_NAME` | Nom de la base de données | `smi_tracking` |
| `DB_USER` | Utilisateur MySQL | `root` |
| `DB_PASS` | Mot de passe MySQL | `` |
| `EXTERNAL_API_URL` | URL de l'API externe pour le forwarding | `https://httpbin.org/post` |

---

## Scopes utilisés

| Scope | Raison |
|---|---|
| `read_orders` | Lecture des commandes via webhook |
| `read_discounts` | Lecture des codes promos existants |
| `write_discounts` | Création des codes promos |
| `write_price_rules` | Création des price rules nécessaires aux discounts |

---

## Endpoints disponibles

| Méthode | Endpoint | Description |
|---|---|---|
| `GET` | `/auth/install?shop=xxx.myshopify.com` | Lance le flow OAuth d'installation |
| `GET` | `/auth/callback` | Callback OAuth — récupère et stocke l'access token |
| `POST` | `/webhooks/orders` | Reçoit les webhooks Shopify (orders/updated) |
| `POST` | `/orders/status` | Met à jour le statut d'une commande |
| `POST` | `/discount-codes` | Crée un code promo dans Shopify |

---

## Exemples d'utilisation

### Mettre à jour le statut d'une commande

**Request :**
```json
POST /orders/status
Content-Type: application/json

{
    "order_id": "123456",
    "status": "accepted"
}
```

**Response :**
```json
{
    "success": true,
    "order_id": "123456",
    "status": "accepted"
}
```

### Rejeter une commande

**Request :**
```json
POST /orders/status
Content-Type: application/json

{
    "order_id": "123456",
    "status": "rejected",
    "reason": "refund"
}
```

### Créer un code promo

**Request :**
```json
POST /discount-codes
Content-Type: application/json

{
    "code": "USER10",
    "type": "percentage",
    "value": 10,
    "starts_at": "2026-05-01T00:00:00Z"
}
```

**Response :**
```json
{
    "success": true,
    "code": "USER10",
    "shopify_id": "20054335651934"
}
```

---

## Architecture du projet

```
smi-tracking-app/
├── .env.example                # Template des variables d'environnement
├── .gitignore                  # Fichiers exclus du repository
├── .htaccess                   # Configuration Apache (routing)
├── index.php                   # Point d'entrée de l'application
├── config/
│   └── database.php            # Connexion PDO MySQL + chargement .env
├── controllers/
│   ├── AuthController.php      # Flow OAuth Shopify
│   ├── WebhookController.php   # Réception webhooks + HMAC + idempotence
│   ├── OrderController.php     # Gestion statuts commandes
│   └── DiscountController.php  # Création codes promos Shopify
├── routes/
│   └── router.php              # Routeur HTTP
├── db/
│   └── schema.sql              # Schéma de la base de données
└── README.md
```

---

## Fonctionnalités techniques

- **OAuth 2.0** — Flow d'installation Shopify complet avec vérification du nonce
- **Vérification HMAC** — Chaque webhook est vérifié avec HMAC-SHA256
- **Idempotence** — Les commandes ne sont jamais traitées en double
- **Retry** — 3 tentatives si l'API externe est indisponible
- **Logs** — Les erreurs sont loggées via `error_log()`
- **Variables d'environnement** — Aucun secret hardcodé dans le code